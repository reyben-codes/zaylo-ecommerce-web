<?php

namespace App\Http\Controllers;

use App\Models\ComplianceWarning;
use App\Models\Product;
use App\Models\SellerApplication;
use App\Models\User;
use App\Notifications\AccountReviewed;
use App\Notifications\ComplianceWarningIssued;
use App\Notifications\ProductReviewed;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Admin Center: seller compliance. New and edited listings wait in "Pending review" until an admin approves
 * them (buyers never see them before). Products outside the seller's registered category or matching prohibited
 * words are flagged; the admin can warn the seller, hide the product, or suspend the seller.
 */
class AdminComplianceController extends Controller
{
    public function index(Request $request)
    {
        if (! Schema::hasTable('compliance_warnings')) {
            return view('admin.setup-needed', ['title' => 'Seller compliance']);
        }
        $filters = $request->validate([
            'view' => ['nullable', Rule::in(['pending', 'flagged', 'all', 'hidden', 'rejected', 'warnings'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim($filters['search'] ?? '');

        $products = $this->reviewedProducts($search);
        $groups = [
            // Archived listings are the seller's to restore, so they never wait for a decision.
            'pending' => fn ($row) => $row['product']->is_pending_review && ! $row['product']->is_archived,
            'flagged' => fn ($row) => $row['flags'] && $row['product']->is_listed,
            'all' => fn () => true,
            'hidden' => fn ($row) => ! $row['product']->is_active && $row['product']->review_status === Product::REVIEW_APPROVED,
            'rejected' => fn ($row) => $row['product']->is_rejected,
        ];
        $counts = array_map(fn ($test) => $products->filter($test)->count(), $groups) + ['warnings' => ComplianceWarning::count()];
        // Open on the queue while listings are waiting; otherwise on what was flagged.
        $default = $counts['pending'] > 0 ? 'pending' : 'flagged';
        $view = $filters['view'] ?? $default;
        $listed = $view === 'warnings' ? collect() : $products->filter($groups[$view]);

        return view('admin.compliance', [
            'view' => $view,
            'defaultView' => $default,
            'search' => $search,
            'counts' => $counts,
            'products' => $view === 'warnings' ? null : $this->paginate($listed->values(), $request),
            'warnings' => $view === 'warnings'
                ? ComplianceWarning::with(['seller', 'product', 'issuer'])->latest()->paginate(20)->withQueryString()
                : null,
            'reasons' => ComplianceWarning::REASONS,
            'actions' => ComplianceWarning::ACTIONS,
        ]);
    }

    /** Warn the seller about a product, and optionally hide it or suspend the seller. */
    public function warn(Request $request, Product $product)
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(ComplianceWarning::REASONS))],
            'details' => ['required', 'string', 'min:10', 'max:1000'],
            'action' => ['required', Rule::in(array_keys(ComplianceWarning::ACTIONS))],
        ], [
            'details.required' => 'Tell the seller what is wrong and how to fix it.',
            'details.min' => 'Give the seller at least a sentence (10 characters) explaining the problem.',
        ]);
        $seller = $this->ownerOf($product);
        abort_unless($seller, 422, 'This product has no seller account.');

        $warning = DB::transaction(function () use ($data, $product, $seller, $request) {
            $warning = ComplianceWarning::create($data + ['seller_id' => $seller->id, 'product_id' => $product->id, 'issued_by' => $request->user()->id]);
            if (in_array($data['action'], ['product_hidden', 'suspended'], true)) {
                $product->update(['is_active' => false]);
            }
            if ($data['action'] === 'suspended') {
                $seller->update(['status' => 'suspended', 'approved_at' => null, 'approved_by' => null]);
            }

            return $warning;
        });

        $this->mail($seller, new ComplianceWarningIssued($warning->load('product')));
        if ($data['action'] === 'suspended') {
            $this->mail($seller, new AccountReviewed('suspended', $data['details']));
        }

        $done = match ($data['action']) {
            'product_hidden' => 'warned and the product is hidden from buyers',
            'suspended' => 'suspended and the product is hidden',
            default => 'warned',
        };

        return back()->with('status', "{$seller->name} was {$done}.");
    }

    /** Approve a listing so buyers can see it, or reject it with a reason the seller can fix. */
    public function review(Request $request, Product $product)
    {
        abort_unless(Product::usesReview(), 404);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'min:10', 'max:1000'],
        ], [
            'reason.required_if' => 'Tell the seller why the product was rejected and what to change.',
            'reason.min' => 'Give the seller at least a sentence (10 characters) explaining the problem.',
        ]);
        $approved = $data['decision'] === 'approve';
        $product->forceFill([
            'review_status' => $approved ? Product::REVIEW_APPROVED : Product::REVIEW_REJECTED,
            'review_note' => $approved ? null : $data['reason'],
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ])->save();

        if ($seller = $this->ownerOf($product)) {
            $this->mail($seller, new ProductReviewed($product));
        }

        return back()->with('status', $approved
            ? ($product->is_active ? "{$product->name} is approved and now visible to buyers." : "{$product->name} is approved. Its seller has it hidden, so buyers see it once they switch it on.")
            : "{$product->name} was rejected. The seller was emailed your reason.");
    }

    /** Hide a product from buyers, or put a reviewed product back on sale. */
    public function toggle(Product $product)
    {
        // The seller archived it; showing it again is the seller's call (they restore it from their archive).
        if ($product->is_archived) {
            return back()->with('status', "{$product->name} was archived by its seller, so only the seller can restore it.");
        }
        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('status', $product->name.($product->is_active ? ' is visible to buyers again.' : ' is hidden from buyers.'));
    }

    /** Active and hidden products with what needs a closer look. */
    private function reviewedProducts(string $search): Collection
    {
        $legacy = Product::usesLegacySchema();
        $products = Product::query()
            ->with($legacy ? ['seller'] : ['seller.owner', 'category'])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->latest('id')->get();
        $owners = $products->mapWithKeys(fn (Product $product) => [$product->id => $this->ownerOf($product)]);
        $registered = Schema::hasTable('seller_applications')
            ? SellerApplication::whereIn('user_id', $owners->filter()->pluck('id')->unique())->pluck('shop_category', 'user_id')
            : collect();
        $warned = ComplianceWarning::selectRaw('product_id, count(*) as total')->groupBy('product_id')->pluck('total', 'product_id');

        return $products->map(function (Product $product) use ($owners, $registered, $warned) {
            $owner = $owners[$product->id];
            $sellerCategory = $owner ? ($registered[$owner->id] ?? null) : null;
            $flags = [];
            if ($sellerCategory && ! $this->inCategory($product->category_key, $sellerCategory)) {
                $flags[] = ['wrong_category', 'Listed in '.$product->category_label.', but the seller registered for '.(config('marketplace.categories')[$sellerCategory] ?? $sellerCategory)];
            }
            if ($words = $this->prohibitedWords($product)) {
                $flags[] = ['prohibited', 'Mentions '.implode(', ', array_map(fn ($word) => '"'.$word.'"', $words))];
            }

            return [
                'product' => $product,
                'owner' => $owner,
                'shop' => $owner ? \App\Services\MarketplaceLedger::sellerName($owner) : 'No seller',
                'registered' => $sellerCategory ? (config('marketplace.categories')[$sellerCategory] ?? $sellerCategory) : null,
                'flags' => $flags,
                'warnings' => (int) ($warned[$product->id] ?? 0),
            ];
        })->sortByDesc(fn ($row) => count($row['flags']))->values();
    }

    private function inCategory(?string $productCategory, string $sellerCategory): bool
    {
        $allowed = config('marketplace.category_groups')[$sellerCategory] ?? [$sellerCategory];

        return in_array($productCategory, $allowed, true);
    }

    private function prohibitedWords(Product $product): array
    {
        $text = mb_strtolower($product->name.' '.$product->description);

        return array_values(array_filter(config('marketplace.prohibited_keywords', []), fn ($word) => preg_match('/\b'.preg_quote($word, '/').'\b/u', $text)));
    }

    private function ownerOf(Product $product): ?User
    {
        return Product::usesLegacySchema() ? $product->seller : $product->seller?->owner;
    }

    private function paginate(Collection $rows, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));

        return (new LengthAwarePaginator($rows->forPage($page, 20)->values(), $rows->count(), 20, $page, ['path' => $request->url()]))->withQueryString();
    }

    private function mail(User $user, $notification): void
    {
        try {
            $user->notify($notification);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
