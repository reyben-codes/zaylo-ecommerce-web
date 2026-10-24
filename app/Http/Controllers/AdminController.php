<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UpdatesAccountSettings;
use App\Models\ActivityLog;
use App\Models\ComplianceWarning;
use App\Models\Dispute;
use App\Models\IdentityVerification;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rider;
use App\Models\SellerApplication;
use App\Models\SupportMessage;
use App\Models\User;
use App\Notifications\AccountReviewed;
use App\Notifications\SellerApplicationReviewed;
use App\Services\MarketplaceLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    use UpdatesAccountSettings;

    public const ROLE_LABELS = [
        'buyer' => 'Buyer',
        'seller' => 'Seller',
        'logistics' => 'Logistics partner',
        'rider' => 'Rider',
        'admin' => 'Administrator',
    ];

    public const STATUS_LABELS = [
        'active' => 'Active',
        'pending' => 'Pending',
        'suspended' => 'Suspended',
        'deactivated' => 'Deactivated',
        'rejected' => 'Rejected',
    ];

    // Account statuses mirrored onto the partner records that have their own approval state.
    private const PARTNER_STATUSES = [
        'active' => 'approved',
        'pending' => 'pending',
        'suspended' => 'suspended',
        'deactivated' => 'suspended',
        'rejected' => 'rejected',
    ];

    public function dashboard(MarketplaceLedger $ledger)
    {
        $roleCounts = collect(array_keys(self::ROLE_LABELS))
            ->mapWithKeys(fn (string $role) => [$role => $this->whereRole(User::query(), $role)->count()]);

        return view('admin.dashboard', [
            'user' => auth()->user(),
            'roleCounts' => $roleCounts,
            'pendingCount' => $this->pendingApplications()->count(),
            'productCount' => Product::visibleToBuyers()->count(),
            'orderCount' => Order::count(),
            'partnerCount' => Schema::hasTable('logistics_providers') ? LogisticsProvider::where('status', 'approved')->count() : 0,
            'pendingApplications' => $this->pendingApplications()->latest()->limit(6)->get(),
            'pendingIdCount' => Schema::hasTable('identity_verifications') ? IdentityVerification::where('status', 'pending')->count() : 0,
            'roleLabels' => self::ROLE_LABELS,
            'attention' => $this->attention(),
            'sales' => $ledger->summary($ledger->rows(now(MarketplaceLedger::TIMEZONE)->subDays(29)->startOfDay()->utc(), now()->utc())),
            'openDisputes' => Schema::hasTable('dispute_messages')
                ? Dispute::with('order')->whereIn('status', ['open', 'in_review'])->latest()->limit(5)->get()
                : collect(),
        ]);
    }

    /** One account: details, role information, activity, cases and warnings, with the status actions. */
    public function showUser(User $user)
    {
        $legacy = Order::usesLegacySchema();
        $buyerOrders = $user->hasRole('buyer') ? Order::where($legacy ? 'user_id' : 'buyer_id', $user->id) : null;

        return view('admin.user', [
            'account' => $user->load($this->partnerRelations()),
            'roleLabels' => self::ROLE_LABELS,
            'statusLabels' => self::STATUS_LABELS,
            'buyerStats' => $buyerOrders ? [
                'orders' => (clone $buyerOrders)->count(),
                'spent' => $legacy ? (float) (clone $buyerOrders)->where('status', 'completed')->sum('total') : (clone $buyerOrders)->sum('total_minor') / 100,
                'recent' => (clone $buyerOrders)->latest()->limit(5)->get(),
            ] : null,
            'sellerStats' => $user->hasRole('seller') ? (function () use ($user) {
                $ledger = app(MarketplaceLedger::class);
                $row = $ledger->bySeller($ledger->rows()->where('seller_id', $user->id))->first();

                return [
                    'shop' => MarketplaceLedger::sellerName($user),
                    'products' => Product::usesLegacySchema() ? Product::where('seller_id', $user->id)->count() : Product::whereIn('seller_id', $user->sellers()->pluck('id'))->count(),
                    'row' => $row,
                ];
            })() : null,
            'disputes' => Schema::hasTable('dispute_messages') ? Dispute::involving($user)->with('order')->latest()->limit(10)->get() : collect(),
            'warnings' => Schema::hasTable('compliance_warnings') ? ComplianceWarning::with('product')->where('seller_id', $user->id)->latest()->get() : collect(),
            'activity' => Schema::hasTable('activity_logs') ? ActivityLog::where('user_id', $user->id)->latest('created_at')->latest('id')->limit(8)->get() : collect(),
        ]);
    }

    /** What is waiting for the admin, most urgent first. */
    private function attention(): array
    {
        $items = [];
        $add = function (int $count, string $label, string $route, string $icon) use (&$items) {
            if ($count > 0) {
                $items[] = compact('count', 'label', 'route', 'icon');
            }
        };
        $add($this->pendingApplications()->count(), 'applications to review', route('admin.registrations'), 'fa-user-check');
        if (Schema::hasTable('dispute_messages')) {
            $add(Dispute::where('status', 'open')->count(), 'new complaints', route('admin.disputes', ['status' => 'open']), 'fa-scale-balanced');
        }
        if (Schema::hasTable('support_messages')) {
            $add(SupportMessage::whereNull('read_at')->whereHas('thread', fn (Builder $thread) => $thread->whereColumn('support_threads.user_id', 'support_messages.sender_id'))->count(), 'unread support messages', route('admin.chat'), 'fa-comments');
        }
        if (Schema::hasTable('identity_verifications')) {
            $add(IdentityVerification::where('status', 'pending')->count(), 'buyer IDs to check', route('admin.identity'), 'fa-id-card');
        }
        if (Product::usesReview() && Schema::hasTable('compliance_warnings')) {
            $add(Product::where('review_status', Product::REVIEW_PENDING)->notArchived()->count(), 'product listings to approve', route('admin.compliance', ['view' => 'pending']), 'fa-box-open');
        }
        $unrecorded = app(MarketplaceLedger::class)->rows()->where('commission_status', 'unrecorded')->count();
        $add($unrecorded, 'completed orders without a recorded commission', route('admin.commissions', ['state' => 'unrecorded']), 'fa-percent');

        return $items;
    }

    /**
     * Seller and logistics partner applications. Riders are reviewed by the partner they applied to.
     */
    public function registrations(Request $request)
    {
        $role = $request->validate(['role' => ['nullable', Rule::in(['seller', 'logistics'])]])['role'] ?? null;
        $counts = [
            'all' => $this->pendingApplications()->count(),
            'seller' => $this->whereRole(User::where('status', 'pending'), 'seller')->count(),
            'logistics' => $this->whereRole(User::where('status', 'pending'), 'logistics')->count(),
        ];
        $applications = $this->pendingApplications()
            ->when($role, fn (Builder $query) => $this->whereRole($query, $role))
            ->with($this->partnerRelations())
            ->oldest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.registrations', [
            'applications' => $applications,
            'counts' => $counts,
            'role' => $role,
            'roleLabels' => self::ROLE_LABELS,
        ]);
    }

    public function users(Request $request)
    {
        $filters = $request->validate([
            'role' => ['nullable', Rule::in(array_keys(self::ROLE_LABELS))],
            'status' => ['nullable', Rule::in(array_keys(self::STATUS_LABELS))],
            'search' => 'nullable|string|max:100',
        ]) + ['role' => null, 'status' => null, 'search' => null];

        $users = User::query()
            ->when($filters['role'], fn (Builder $query) => $this->whereRole($query, $filters['role']))
            ->when($filters['status'], fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(filled($filters['search']), function (Builder $query) use ($filters) {
                $term = '%'.addcslashes($filters['search'], '%_\\').'%';
                $query->where(fn (Builder $nested) => $nested->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->with($this->partnerRelations())
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users', [
            'users' => $users,
            'filters' => $filters,
            'roleLabels' => self::ROLE_LABELS,
            'statusLabels' => self::STATUS_LABELS,
        ]);
    }

    public function logistics()
    {
        $partners = LogisticsProvider::with('owner')
            ->withCount([
                'riders as approved_riders_count' => fn (Builder $query) => $query->where('status', 'approved'),
                'riders as pending_riders_count' => fn (Builder $query) => $query->where('status', 'pending'),
            ])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->paginate(20);

        return view('admin.logistics', compact('partners'));
    }

    /** A logistics partner's business details and documents, for approval. */
    public function logisticsApplication(LogisticsProvider $provider)
    {
        return view('admin.logistics-application', [
            'provider' => $provider->load('owner'),
            'applicant' => $provider->owner,
            'documents' => LogisticsProvider::DOCUMENTS,
            'regions' => LogisticsProvider::serviceRegions(),
        ]);
    }

    public function logisticsDocument(LogisticsProvider $provider, string $document)
    {
        abort_unless(array_key_exists($document, LogisticsProvider::DOCUMENTS), 404);
        $path = $provider->{$document.'_path'};
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        // Labels such as "Mayor's / Business Permit" are not valid file names, so name the download after the field.
        return Storage::disk('local')->response($path, str_replace('_', '-', $document).'.'.pathinfo($path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function updateUserStatus(Request $request, User $user)
    {
        abort_if($user->is(auth()->user()), 422, 'You cannot change your own account status.');
        $application = $user->hasRole('seller') && Schema::hasTable('seller_applications') ? $user->sellerApplication : null;
        $partnerApplied = $user->hasRole('logistics') && $user->awaitsLogisticsApproval() && $user->logisticsProvider?->hasApplication();
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(self::STATUS_LABELS))],
            // Rejected sellers and logistics partners see the reason, so it is required when one has applied.
            'reason' => [$application || $partnerApplied ? 'required_if:status,rejected' : 'nullable', 'nullable', 'string', 'max:500'],
        ], ['reason.required_if' => 'Give the reason for rejecting this application. The applicant sees it.']);
        $deciding = in_array($user->status, ['pending', 'rejected'], true) && in_array($data['status'], ['active', 'rejected'], true);
        $previous = $user->status;

        DB::transaction(function () use ($user, $data, $application) {
            $user->update([
                'status' => $data['status'],
                'approved_at' => $data['status'] === 'active' ? ($user->approved_at ?? now()) : null,
                'approved_by' => $data['status'] === 'active' ? ($user->approved_by ?? auth()->id()) : null,
            ]);

            $partnerStatus = self::PARTNER_STATUSES[$data['status']];
            if ($user->hasRole('logistics') && Schema::hasTable('logistics_providers')) {
                $review = Schema::hasColumn('logistics_providers', 'reviewed_at') && in_array($data['status'], ['active', 'rejected'], true)
                    ? ['reviewed_at' => now(), 'rejection_reason' => $data['status'] === 'rejected' ? ($data['reason'] ?? null) : null]
                    : [];
                LogisticsProvider::where('user_id', $user->id)->update(['status' => $partnerStatus] + ($partnerStatus === 'approved' ? ['approved_at' => now()] : []) + $review);
            }
            if ($user->hasRole('rider') && Schema::hasTable('riders')) {
                Rider::where('user_id', $user->id)->update(['status' => $partnerStatus, 'reviewed_at' => now()]);
            }
            if ($user->hasRole('seller') && ! Product::usesLegacySchema()) {
                $user->sellers()->update(['status' => $partnerStatus]);
            }
            if ($application && in_array($data['status'], ['active', 'rejected', 'pending'], true)) {
                $application->update([
                    'status' => $partnerStatus,
                    'rejection_reason' => $data['status'] === 'rejected' ? $data['reason'] : null,
                    'reviewed_at' => $data['status'] === 'pending' ? null : now(),
                    'reviewed_by' => $data['status'] === 'pending' ? null : auth()->id(),
                ]);
            }
        });

        // Email the decision: sellers get their application result; everyone else a general account notice.
        $notice = match (true) {
            $user->hasRole('seller') && $deciding => new SellerApplicationReviewed($data['status'] === 'active', $data['reason'] ?? null),
            $deciding, in_array($data['status'], ['suspended', 'deactivated'], true),
            $data['status'] === 'active' && in_array($previous, ['suspended', 'deactivated'], true) => new AccountReviewed($data['status'], $data['reason'] ?? null),
            default => null,
        };
        if ($notice) {
            try {
                $user->notify($notice);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $message = match ($data['status']) {
            'active' => in_array($previous, ['suspended', 'deactivated'], true) ? "{$user->name} is active again." : "{$user->name} is approved and can now sign in.",
            'suspended' => "{$user->name} has been suspended.",
            'deactivated' => "{$user->name} has been deactivated.",
            'rejected' => "{$user->name}'s application was rejected.",
            default => "{$user->name} is back to pending review.",
        };

        return back()->with('status', $message);
    }

    /**
     * Everything a seller submitted, with their documents, for the approve / reject decision.
     */
    public function sellerApplication(User $user)
    {
        abort_unless($user->hasRole('seller'), 404);
        $application = Schema::hasTable('seller_applications') ? $user->sellerApplication : null;

        return view('admin.seller-application', [
            'applicant' => $user,
            'application' => $application,
            'documents' => SellerApplication::DOCUMENTS,
        ]);
    }

    public function sellerApplicationDocument(User $user, string $document)
    {
        abort_unless(array_key_exists($document, SellerApplication::DOCUMENTS), 404);
        $path = $user->sellerApplication?->{$document.'_path'};
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, SellerApplication::DOCUMENTS[$document].'.'.pathinfo($path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    /**
     * Buyer ID checks. Approved buyers can check out; rejected ones see the reason and can upload again.
     */
    public function identity(Request $request)
    {
        $enabled = Schema::hasTable('identity_verifications');
        $status = $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])]])['status'] ?? 'pending';
        $counts = $enabled
            ? IdentityVerification::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')
            : collect();
        $verifications = $enabled
            ? IdentityVerification::where('status', $status)->with(['user', 'reviewer'])
                ->when($status === 'pending', fn (Builder $query) => $query->oldest('updated_at'), fn (Builder $query) => $query->latest('reviewed_at'))
                ->paginate(20)->withQueryString()
            : null;

        return view('admin.identity', [
            'enabled' => $enabled,
            'status' => $status,
            'counts' => collect(['pending', 'approved', 'rejected'])->mapWithKeys(fn ($key) => [$key => (int) $counts->get($key, 0)]),
            'verifications' => $verifications,
        ]);
    }

    /**
     * Streams a private ID document to an administrator; there is no public URL for these files.
     */
    public function identityDocument(IdentityVerification $verification)
    {
        abort_unless(Storage::disk('local')->exists($verification->document_path), 404);

        return Storage::disk('local')->response($verification->document_path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function reviewIdentity(Request $request, IdentityVerification $verification)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => 'exclude_unless:decision,reject|required|string|max:500',
        ], ['reason.required' => 'Tell the buyer why the ID was rejected so they can upload a better one.']);

        DB::transaction(function () use ($verification, $data) {
            $locked = IdentityVerification::whereKey($verification->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'This ID was already reviewed. Refresh the page.']);
            }
            $approved = $data['decision'] === 'approve';
            $locked->update([
                'status' => $approved ? 'approved' : 'rejected',
                'rejection_reason' => $approved ? null : $data['reason'],
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);
            if ($approved) {
                $locked->user->forceFill(['identity_verified_at' => now()])->save();
            }
        });

        $name = $verification->user?->name ?? 'The buyer';

        return back()->with('status', $data['decision'] === 'approve'
            ? "{$name}'s ID is approved. They can now check out."
            : "{$name}'s ID was rejected. They'll see your reason and can upload again.");
    }

    public function account()
    {
        return view('admin.account', ['user' => auth()->user()]);
    }

    /**
     * Admin sections that are still design mockups show a placeholder instead of sample data.
     */
    private function pendingApplications(): Builder
    {
        return User::where('status', 'pending')->where(fn (Builder $query) => $this->whereRole($query, 'seller')
            ->orWhere(fn (Builder $nested) => $this->whereRole($nested, 'logistics')));
    }

    /**
     * Roles live in users.role on the deployed database and in the roles table on fresh installs.
     */
    private function whereRole(Builder $query, string $role): Builder
    {
        $names = $role === 'rider' ? ['rider', 'courier'] : [$role];

        return Schema::hasColumn('users', 'role')
            ? $query->whereIn('role', $names)
            : $query->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', $names));
    }

    private function partnerRelations(): array
    {
        return array_values(array_filter([
            Schema::hasTable('logistics_providers') ? 'logisticsProvider' : null,
            Schema::hasTable('riders') ? 'rider.provider' : null,
            Schema::hasTable('seller_applications') ? 'sellerApplication' : null,
            Schema::hasColumn('users', 'role') ? null : 'roles',
        ]));
    }
}
