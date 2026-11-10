<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Models\VoucherSeller;
use App\Services\Vouchers;
use App\Support\Shop;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admins create vouchers and see which shops joined; sellers join or decline the ones they are invited to
 * (App\Services\Vouchers).
 */
class VoucherController extends Controller
{
    public function __construct(private readonly Vouchers $vouchers) {}

    public function admin()
    {
        abort_unless(Vouchers::ready(), 404);

        return view('admin.vouchers', [
            'vouchers' => Voucher::withCount([
                'sellers as joined_count' => fn ($q) => $q->where('status', VoucherSeller::ACCEPTED),
                'sellers as declined_count' => fn ($q) => $q->where('status', VoucherSeller::DECLINED),
                'sellers as pending_count' => fn ($q) => $q->where('status', VoucherSeller::PENDING),
            ])->latest('id')->paginate(20),
            'types' => Voucher::TYPES,
            'shops' => $this->vouchers->shops(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Vouchers::ready(), 404);
        $data = $request->validate([
            'code' => ['required', 'string', 'min:4', 'max:30', 'regex:/^[A-Za-z0-9-]+$/D', Rule::unique('vouchers', 'code')],
            'name' => ['nullable', 'string', 'max:80'],
            'target_type' => ['nullable', Rule::in(['all', 'specific'])],
            'target_seller_id' => ['nullable', 'required_if:target_type,specific', 'integer'],
            'type' => ['required', Rule::in(array_keys(Voucher::TYPES))],
            'value' => ['nullable', 'required_unless:type,free_shipping', 'numeric', 'min:1', $request->input('type') === 'percent' ? 'max:90' : 'max:100000'],
            'min_spend' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'max_discount' => ['nullable', 'numeric', 'min:1', 'max:100000'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at', 'after:now'],
        ], [
            'code.regex' => 'Use letters, numbers and dashes only.',
            'value.required_unless' => 'Enter the discount.',
            'target_seller_id.required_if' => 'Select a shop to target this voucher.',
        ]);
        $data['code'] = strtoupper($data['code']);
        if ($data['type'] === 'free_shipping') {
            $data['value'] = null;
            $data['max_discount'] = null;
        }

        $targetType = $data['target_type'] ?? 'all';
        $targetSellerId = $data['target_seller_id'] ?? null;
        unset($data['target_type'], $data['target_seller_id']);

        if ($targetType === 'specific' && $targetSellerId) {
            $data['seller_id'] = (int) $targetSellerId;
        } else {
            $data['seller_id'] = null;
        }
        $data['created_by'] = $request->user()->id;

        if (! empty($data['starts_at'])) {
            $data['starts_at'] = \Illuminate\Support\Carbon::parse($data['starts_at'], 'Asia/Manila')->utc();
        }
        if (! empty($data['expires_at'])) {
            $data['expires_at'] = \Illuminate\Support\Carbon::parse($data['expires_at'], 'Asia/Manila')->utc();
        }

        $voucher = $this->vouchers->create($data);

        if ($voucher->seller_id) {
            $shopName = $this->vouchers->shopName((int) $voucher->seller_id);

            return back()->with('status', "{$voucher->code} was created for {$shopName}. The shop was invited to join; it applies to their products once they join.");
        }

        return back()->with('status', $voucher->needsOptIn()
            ? "{$voucher->code} was created. Every shop was invited to join; it applies to a shop's products once that shop joins."
            : "{$voucher->code} was created. Free shipping applies to every shop and ZAYLO pays it.");
    }

    public function toggle(Voucher $voucher)
    {
        $voucher->update(['is_active' => ! $voucher->is_active]);

        return back()->with('status', $voucher->code.($voucher->is_active ? ' is on again.' : ' is turned off; buyers can no longer use it.'));
    }

    public function seller(Request $request)
    {
        abort_unless(Vouchers::ready(), 404);

        return view('seller.vouchers', [
            'invitations' => $this->vouchers->invitationsFor($request->user()),
            'shopVouchers' => $this->vouchers->shopVouchersFor($request->user()),
        ]);
    }

    public function storeSeller(Request $request)
    {
        abort_unless(Vouchers::ready(), 404);
        $shopId = Shop::idForSeller($request->user());
        abort_unless($shopId, 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'min:4', 'max:30', 'regex:/^[A-Za-z0-9-]+$/D', Rule::unique('vouchers', 'code')],
            'name' => ['nullable', 'string', 'max:80'],
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'numeric', 'min:1', $request->input('type') === 'percent' ? 'max:90' : 'max:100000'],
            'min_spend' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'max_discount' => ['nullable', 'numeric', 'min:1', 'max:100000'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at', 'after:now'],
        ], [
            'code.regex' => 'Use letters, numbers and dashes only.',
            'value.required' => 'Enter the discount amount or percent.',
        ]);
        $data['code'] = strtoupper($data['code']);

        if (! empty($data['starts_at'])) {
            $data['starts_at'] = \Illuminate\Support\Carbon::parse($data['starts_at'], 'Asia/Manila')->utc();
        }
        if (! empty($data['expires_at'])) {
            $data['expires_at'] = \Illuminate\Support\Carbon::parse($data['expires_at'], 'Asia/Manila')->utc();
        }

        $voucher = $this->vouchers->createForSeller($data, $shopId, $request->user());

        return back()->with('status', "Shop voucher {$voucher->code} created and active for your products.");
    }

    public function toggleSeller(Request $request, Voucher $voucher)
    {
        $shopId = Shop::idForSeller($request->user());
        abort_unless($shopId && (int) $voucher->seller_id === $shopId && (int) $voucher->created_by === $request->user()->id, 403);

        $voucher->update(['is_active' => ! $voucher->is_active]);

        return back()->with('status', $voucher->code.($voucher->is_active ? ' is active again.' : ' is paused; buyers can no longer use it.'));
    }

    public function destroySeller(Request $request, Voucher $voucher)
    {
        $shopId = Shop::idForSeller($request->user());
        abort_unless($shopId && (int) $voucher->seller_id === $shopId && (int) $voucher->created_by === $request->user()->id, 403);

        if ($voucher->used_count > 0) {
            $voucher->update(['is_active' => false]);

            return back()->with('status', "{$voucher->code} has been used by customers so it was paused instead of deleted.");
        }

        $voucher->delete();

        return back()->with('status', "Voucher {$voucher->code} was deleted.");
    }

    public function respond(Request $request, Voucher $voucher)
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['join', 'decline'])]]);
        $shopId = Shop::idForSeller($request->user());
        abort_unless($shopId, 403);
        $join = $data['decision'] === 'join';
        $this->vouchers->respond($voucher, $shopId, $join);

        return back()->with('status', $join
            ? "You joined {$voucher->code}. Buyers can use it on your products, and your shop pays the discount."
            : "You declined {$voucher->code}. It does not apply to your products.");
    }
}
