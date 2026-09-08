<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveAddressRequest;
use App\Models\Address;
use App\Models\User;
use App\Services\PsgcService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        return view('addresses.index', [
            'addresses' => $request->user()->addresses()->orderByDesc('is_default')->orderBy('id')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return view('addresses.form', [
            'address' => new Address(['recipient_name' => $request->user()->name, 'phone' => $request->user()->phone, 'label' => 'Home']),
            'returnTo' => $request->query('return_to') === 'checkout' && $request->user()->role === 'buyer' ? 'checkout' : null,
        ]);
    }

    public function edit(Request $request, Address $address)
    {
        $this->owned($request, $address);

        return view('addresses.form', [
            'address' => $address,
            'returnTo' => $request->query('return_to') === 'checkout' && $request->user()->role === 'buyer' ? 'checkout' : null,
        ]);
    }

    public function store(SaveAddressRequest $request, PsgcService $psgc)
    {
        return $this->save($request, $psgc);
    }

    public function update(SaveAddressRequest $request, Address $address, PsgcService $psgc)
    {
        return $this->save($request, $psgc, $address);
    }

    private function save(SaveAddressRequest $request, PsgcService $psgc, ?Address $address = null)
    {
        try {
            $data = $request->addressData($psgc);
        } catch (ServiceUnavailableHttpException $exception) {
            return back()->withInput()->withErrors(['region_code' => $exception->getMessage()]);
        }
        $saved = DB::transaction(function () use ($request, $address, $data) {
            // Serialize all address/default mutations for this account.
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $current = $address ? $user->addresses()->whereKey($address->id)->firstOrFail() : new Address;
            $makeDefault = $data['is_default'] || $current->is_default
                || ! $user->addresses()->where('is_default', true)->exists();
            if ($makeDefault) {
                $user->addresses()->when($current->exists, fn ($query) => $query->where('id', '!=', $current->id))
                    ->update(['is_default' => false]);
            }
            $current->fill($data);
            $current->user_id = $user->id;
            $current->is_default = $makeDefault;
            $current->save();

            return $current;
        });

        if ($request->input('return_to') === 'checkout' && $request->user()->role === 'buyer') {
            return redirect()->route('buyer.cart')->with('selected_address_id', $saved->id)->with('status', 'Address saved.');
        }

        return redirect()->route('addresses.index')->with('status', 'Address saved.');
    }

    public function makeDefault(Request $request, Address $address)
    {
        $this->owned($request, $address);
        DB::transaction(function () use ($request, $address) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $current = $user->addresses()->whereKey($address->id)->firstOrFail();
            $user->addresses()->where('id', '!=', $current->id)->update(['is_default' => false]);
            $current->update(['is_default' => true]);
        });

        return back()->with('status', 'Default address updated.');
    }

    public function destroy(Request $request, Address $address)
    {
        $this->owned($request, $address);
        DB::transaction(function () use ($request, $address) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $current = $user->addresses()->whereKey($address->id)->firstOrFail();
            $wasDefault = $current->is_default;
            $current->delete();
            if ($wasDefault) {
                $user->addresses()->update(['is_default' => false]);
                $user->addresses()->orderBy('id')->first()?->update(['is_default' => true]);
            }
        });

        return redirect()->route('addresses.index')->with('status', 'Address deleted. Existing orders are unchanged.');
    }

    private function owned(Request $request, Address $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 403);
    }
}
