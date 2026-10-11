<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveAddressRequest;
use App\Models\Address;
use App\Services\AddressBook;
use App\Services\PsgcService;
use Illuminate\Http\Request;
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
        $label = in_array($request->query('type'), ['Home', 'Work', 'School', 'Other'], true)
            ? $request->query('type')
            : 'Home';

        return view('addresses.form', [
            'address' => new Address(['recipient_name' => $request->user()->name, 'phone' => $request->user()->phone, 'label' => $label]),
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
        $saved = app(AddressBook::class)->save($request->user(), $data, $address);

        if ($request->input('return_to') === 'checkout' && $request->user()->role === 'buyer') {
            return redirect()->route('buyer.checkout.show')->with('selected_address_id', $saved->id)->with('status', 'Address saved.');
        }

        return redirect()->route('addresses.index')->with('status', 'Address saved.');
    }

    public function makeDefault(Request $request, Address $address)
    {
        $this->owned($request, $address);
        app(AddressBook::class)->makeDefault($request->user(), $address);

        return back()->with('status', 'Default address updated.');
    }

    public function destroy(Request $request, Address $address)
    {
        $this->owned($request, $address);
        app(AddressBook::class)->delete($request->user(), $address);

        return redirect()->route('addresses.index')->with('status', 'Address deleted. Existing orders are unchanged.');
    }

    private function owned(Request $request, Address $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 403);
    }
}
