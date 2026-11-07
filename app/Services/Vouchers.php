<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherSeller;
use App\Notifications\VoucherInvitation;
use App\Support\Shop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Vouchers and who pays for them.
 *
 *  - Free shipping: global, no opt-in; ZAYLO pays the shipping it removes.
 *  - % or ₱ off: every shop is invited (notification and email). A shop that joins has the voucher applied to its
 *    items and pays the discount; a shop that declines (or has not answered) is left out.
 */
class Vouchers
{
    public static function ready(): bool
    {
        return Schema::hasTable('voucher_sellers') && Schema::hasColumn('vouchers', 'value');
    }

    public function create(array $data): Voucher
    {
        $voucher = DB::transaction(function () use ($data) {
            $voucher = Voucher::create($data + [
                'funded_by' => $data['type'] === 'free_shipping' ? 'zaylo' : 'seller',
                'is_active' => true,
                'used_count' => 0,
            ]);
            if ($voucher->needsOptIn()) {
                foreach ($this->shops() as $shop) {
                    VoucherSeller::create(['voucher_id' => $voucher->id, 'seller_id' => $shop['id'], 'status' => VoucherSeller::PENDING]);
                }
            }

            return $voucher;
        });

        if ($voucher->needsOptIn()) {
            foreach ($this->shops() as $shop) {
                try {
                    $shop['owner']->notify(new VoucherInvitation($voucher));
                } catch (\Throwable $error) {
                    // The invitation is on the seller's Vouchers page either way.
                    Log::warning('Voucher invitation email failed', ['voucher' => $voucher->id, 'error' => $error->getMessage()]);
                }
            }
        }

        return $voucher;
    }

    /** The shop joins (the voucher applies to its items, and it pays the discount) or declines (it is left out). */
    public function respond(Voucher $voucher, int $shopId, bool $join): VoucherSeller
    {
        $row = VoucherSeller::where('voucher_id', $voucher->id)->where('seller_id', $shopId)->firstOrFail();
        $row->update(['status' => $join ? VoucherSeller::ACCEPTED : VoucherSeller::DECLINED, 'responded_at' => now()]);

        return $row;
    }

    /** @return Collection<int, VoucherSeller> the shop's invitations, newest first */
    public function invitationsFor(User $seller): Collection
    {
        $shopId = Shop::idForSeller($seller);
        if (! self::ready() || ! $shopId) {
            return collect();
        }

        return VoucherSeller::with('voucher')->where('seller_id', $shopId)->latest('id')->get();
    }

    public function pendingCount(User $seller): int
    {
        $shopId = Shop::idForSeller($seller);

        return self::ready() && $shopId
            ? VoucherSeller::where('seller_id', $shopId)->where('status', VoucherSeller::PENDING)
                ->whereHas('voucher', fn ($q) => $q->where('is_active', true))->count()
            : 0;
    }

    /** @return array<int, int> shop ids that joined */
    public function joinedShops(Voucher $voucher): array
    {
        if ($voucher->isFreeShipping() || ! self::ready()) {
            return [];
        }

        return VoucherSeller::where('voucher_id', $voucher->id)->where('status', VoucherSeller::ACCEPTED)->pluck('seller_id')->map(fn ($id) => (int) $id)->all();
    }

    /** The discount one shop's order gets: nothing unless the shop joined and the order reaches the minimum spend. */
    public function discountFor(?Voucher $voucher, int $shopId, float $subtotal, ?array $joined = null): float
    {
        if (! $voucher || $voucher->isFreeShipping() || ! self::ready()) {
            return 0.0;
        }
        $joined ??= $this->joinedShops($voucher);
        if (! in_array($shopId, $joined, true) || ($voucher->min_spend && $subtotal < $voucher->min_spend)) {
            return 0.0;
        }
        $discount = $voucher->type === 'percent'
            ? $subtotal * ((float) $voucher->value) / 100
            : (float) $voucher->value;
        if ($voucher->type === 'percent' && $voucher->max_discount) {
            $discount = min($discount, $voucher->max_discount);
        }

        return round(max(0, min($discount, $subtotal)), 2);
    }

    /**
     * The discount on a cart, per shop.
     *
     * @param  Collection  $items  cart items with product loaded
     * @return array<int, float> shop id => discount
     */
    public function cartDiscounts(?Voucher $voucher, Collection $items): array
    {
        if (! $voucher || $voucher->isFreeShipping()) {
            return [];
        }
        $joined = $this->joinedShops($voucher);

        return $items->groupBy(fn ($item) => (int) ($item->product->seller_id ?? 0))
            ->map(fn (Collection $group, int $shopId) => $this->discountFor($voucher, $shopId, (float) $group->sum(fn ($item) => $item->unitPrice() * $item->quantity), $joined))
            ->filter()->all();
    }

    /** Every open shop with its owner, to invite. */
    private function shops(): Collection
    {
        if (Product::usesLegacySchema()) {
            return User::where('role', 'seller')->where('status', 'active')->get()
                ->map(fn (User $owner) => ['id' => (int) $owner->id, 'owner' => $owner]);
        }

        return Seller::with('owner')->where('status', 'approved')->get()
            ->filter(fn (Seller $shop) => $shop->owner)
            ->map(fn (Seller $shop) => ['id' => (int) $shop->id, 'owner' => $shop->owner])->values();
    }
}
