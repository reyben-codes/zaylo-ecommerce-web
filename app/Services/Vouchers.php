<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherClaim;
use App\Models\VoucherSeller;
use App\Notifications\VoucherInvitation;
use App\Support\Shop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

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
            if (! empty($data['seller_id'])) {
                $target = $this->targetShops((int) $data['seller_id'])->first();
                if ($target) {
                    $data['seller_id'] = $target['id'];
                }
            }
            $voucher = Voucher::create($data + [
                'funded_by' => $data['type'] === 'free_shipping' ? 'zaylo' : 'seller',
                'is_active' => true,
                'used_count' => 0,
            ]);
            if ($voucher->needsOptIn()) {
                $shops = $voucher->seller_id
                    ? $this->targetShops((int) $voucher->seller_id)
                    : $this->shops();
                foreach ($shops as $shop) {
                    VoucherSeller::create(['voucher_id' => $voucher->id, 'seller_id' => $shop['id'], 'status' => VoucherSeller::PENDING]);
                }
            }

            return $voucher;
        });

        if ($voucher->needsOptIn()) {
            $shops = $voucher->seller_id
                ? $this->targetShops((int) $voucher->seller_id)
                : $this->shops();
            foreach ($shops as $shop) {
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

    /** A seller creates a voucher for their own shop; it is immediately accepted. */
    public function createForSeller(array $data, int $shopId, User $owner): Voucher
    {
        return DB::transaction(function () use ($data, $shopId, $owner) {
            $voucher = Voucher::create($data + [
                'seller_id' => $shopId,
                'created_by' => $owner->id,
                'funded_by' => 'seller',
                'is_active' => true,
                'used_count' => 0,
            ]);

            VoucherSeller::create([
                'voucher_id' => $voucher->id,
                'seller_id' => $shopId,
                'status' => VoucherSeller::ACCEPTED,
                'responded_at' => now(),
            ]);

            return $voucher;
        });
    }

    /** The shop joins (the voucher applies to its items, and it pays the discount) or declines (it is left out). */
    public function respond(Voucher $voucher, int $shopId, bool $join): VoucherSeller
    {
        $row = VoucherSeller::where('voucher_id', $voucher->id)->where('seller_id', $shopId)->firstOrFail();
        $row->update(['status' => $join ? VoucherSeller::ACCEPTED : VoucherSeller::DECLINED, 'responded_at' => now()]);

        return $row;
    }

    /**
     * A buyer claims a voucher.
     *
     * If the voucher has a usage limit (e.g. 1 or N uses), total claims are capped at that limit.
     * Once reached, no other buyers can claim it.
     */
    public function claim(Voucher $voucher, User $buyer): VoucherClaim
    {
        return DB::transaction(function () use ($voucher, $buyer) {
            $locked = Voucher::lockForUpdate()->find($voucher->id);
            if (! $locked || ! $locked->isAvailable()) {
                throw ValidationException::withMessages([
                    'voucher' => 'This voucher is no longer active or available.',
                ]);
            }

            $existing = VoucherClaim::where('voucher_id', $locked->id)
                ->where('user_id', $buyer->id)
                ->first();
            if ($existing) {
                return $existing;
            }

            if ($locked->usage_limit !== null) {
                $claimsCount = VoucherClaim::where('voucher_id', $locked->id)->count();
                if ($claimsCount >= $locked->usage_limit) {
                    throw ValidationException::withMessages([
                        'voucher' => 'This voucher has already been claimed by another buyer.',
                    ]);
                }
            }

            return VoucherClaim::create([
                'voucher_id' => $locked->id,
                'user_id' => $buyer->id,
                'claimed_at' => now(),
            ]);
        });
    }

    /** @return Collection<int, VoucherSeller> the shop's invitations from platform/admin, newest first */
    public function invitationsFor(User $seller): Collection
    {
        $shopId = Shop::idForSeller($seller);
        if (! self::ready() || ! $shopId) {
            return collect();
        }

        return VoucherSeller::with('voucher')
            ->where('seller_id', $shopId)
            ->whereHas('voucher', function ($q) use ($seller) {
                $q->whereNull('created_by')
                    ->orWhere('created_by', '!=', $seller->id);
            })
            ->latest('id')
            ->get();
    }

    /** @return Collection<int, Voucher> vouchers created by this seller for their own shop */
    public function shopVouchersFor(User $seller): Collection
    {
        $shopId = Shop::idForSeller($seller);
        if (! self::ready() || ! $shopId) {
            return collect();
        }

        return Voucher::where('seller_id', $shopId)
            ->where('created_by', $seller->id)
            ->latest('id')
            ->get();
    }

    public function pendingCount(User $seller): int
    {
        $shopId = Shop::idForSeller($seller);

        return self::ready() && $shopId
            ? VoucherSeller::where('seller_id', $shopId)->where('status', VoucherSeller::PENDING)
                ->whereHas('voucher', fn ($q) => $q->where('is_active', true)->where(function ($sub) use ($seller) {
                    $sub->whereNull('created_by')->orWhere('created_by', '!=', $seller->id);
                }))->count()
            : 0;
    }

    public function shopName(int $shopId): string
    {
        return Shop::find($shopId)?->name ?? 'Shop #'.$shopId;
    }

    /** Target shops for a specific seller_id. */
    public function targetShops(int $shopId): Collection
    {
        $match = $this->shops()->firstWhere('id', $shopId);
        if ($match) {
            return collect([$match]);
        }

        $shop = Shop::find($shopId);
        if ($shop && $shop->owner) {
            return collect([['id' => $shop->id, 'name' => $shop->name, 'owner' => $shop->owner]]);
        }

        $user = User::find($shopId);
        if ($user && $user->hasRole('seller')) {
            $userShopId = Shop::idForSeller($user);
            if ($userShopId && $userShopId !== $shopId) {
                return $this->targetShops($userShopId);
            }
            $shopModel = $user->sellerShop();

            return collect([['id' => (int) $shopModel->id, 'name' => $shopModel->name, 'owner' => $user]]);
        }

        return collect();
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

    /** Every open shop with its owner and name. */
    public function shops(): Collection
    {
        if (Product::usesLegacySchema()) {
            return User::where('role', 'seller')->where('status', 'active')->get()
                ->map(fn (User $owner) => [
                    'id' => (int) $owner->id,
                    'name' => $owner->sellerStoreName(),
                    'owner' => $owner,
                ]);
        }

        return Seller::with('owner')->where('status', 'approved')->get()
            ->filter(fn (Seller $shop) => $shop->owner)
            ->map(fn (Seller $shop) => [
                'id' => (int) $shop->id,
                'name' => $shop->name,
                'owner' => $shop->owner,
            ])->values();
    }
}
