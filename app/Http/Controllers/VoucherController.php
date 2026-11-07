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
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Vouchers::ready(), 404);
        $data = $request->validate([
            'code' => ['required', 'string', 'min:4', 'max:30', 'regex:/^[A-Za-z0-9-]+$/D', Rule::unique('vouchers', 'code')],
            'name' => ['nullable', 'string', 'max:80'],
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
        ]);
        $data['code'] = strtoupper($data['code']);
        if ($data['type'] === 'free_shipping') {
            $data['value'] = null;
            $data['max_discount'] = null;
        }
        $voucher = $this->vouchers->create($data);

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

        return view('seller.vouchers', ['invitations' => $this->vouchers->invitationsFor($request->user())]);
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
