<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UpdatesAccountSettings;
use App\Models\IdentityVerification;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rider;
use App\Models\SellerApplication;
use App\Notifications\SellerApplicationReviewed;
use App\Models\User;
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
        'rejected' => 'Rejected',
    ];

    // Account statuses mirrored onto the partner records that have their own approval state.
    private const PARTNER_STATUSES = [
        'active' => 'approved',
        'pending' => 'pending',
        'suspended' => 'suspended',
        'rejected' => 'rejected',
    ];

    public function dashboard()
    {
        $roleCounts = collect(array_keys(self::ROLE_LABELS))
            ->mapWithKeys(fn (string $role) => [$role => $this->whereRole(User::query(), $role)->count()]);

        return view('admin.dashboard', [
            'user' => auth()->user(),
            'roleCounts' => $roleCounts,
            'pendingCount' => $this->pendingApplications()->count(),
            'productCount' => Product::where('is_active', true)->count(),
            'orderCount' => Order::count(),
            'partnerCount' => Schema::hasTable('logistics_providers') ? LogisticsProvider::where('status', 'approved')->count() : 0,
            'pendingApplications' => $this->pendingApplications()->latest()->limit(6)->get(),
            'pendingIdCount' => Schema::hasTable('identity_verifications') ? IdentityVerification::where('status', 'pending')->count() : 0,
            'roleLabels' => self::ROLE_LABELS,
        ]);
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

    public function updateUserStatus(Request $request, User $user)
    {
        abort_if($user->is(auth()->user()), 422, 'You cannot change your own account status.');
        $application = $user->hasRole('seller') && Schema::hasTable('seller_applications') ? $user->sellerApplication : null;
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(self::STATUS_LABELS))],
            // Rejected sellers see the reason, so it is required when one has applied.
            'reason' => [$application ? 'required_if:status,rejected' : 'nullable', 'nullable', 'string', 'max:500'],
        ], ['reason.required_if' => 'Give the reason for rejecting this application. The seller sees it.']);
        $deciding = in_array($user->status, ['pending', 'rejected'], true) && in_array($data['status'], ['active', 'rejected'], true);

        DB::transaction(function () use ($user, $data, $application) {
            $user->update([
                'status' => $data['status'],
                'approved_at' => $data['status'] === 'active' ? ($user->approved_at ?? now()) : null,
                'approved_by' => $data['status'] === 'active' ? ($user->approved_by ?? auth()->id()) : null,
            ]);

            $partnerStatus = self::PARTNER_STATUSES[$data['status']];
            if ($user->hasRole('logistics') && Schema::hasTable('logistics_providers')) {
                LogisticsProvider::where('user_id', $user->id)->update(['status' => $partnerStatus] + ($partnerStatus === 'approved' ? ['approved_at' => now()] : []));
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

        if ($user->hasRole('seller') && $deciding) {
            try {
                $user->notify(new SellerApplicationReviewed($data['status'] === 'active', $data['reason'] ?? null));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $message = match ($data['status']) {
            'active' => "{$user->name} is approved and can now sign in.",
            'suspended' => "{$user->name} has been suspended.",
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
    public function comingSoon()
    {
        return view('admin.coming-soon');
    }

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
