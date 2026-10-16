<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use App\Services\PsgcService;
use Illuminate\Validation\Rule;

/**
 * The rider's home address (PSGC region -> province -> city -> barangay plus
 * street and postal code) and gender, shared by sign-up and account editing.
 * Location names always come from PSGC, never from the app.
 */
trait RiderAddressRules
{
    protected function riderAddressRules(): array
    {
        return [
            'region_code' => ['required', 'regex:/^[0-9]{9}$/D'],
            'province_code' => ['nullable', 'regex:/^[0-9]{9}$/D'],
            'city_municipality_code' => ['required', 'regex:/^[0-9]{9}$/D'],
            'barangay_code' => ['required', 'regex:/^[0-9]{9}$/D'],
            'line1' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'regex:/^[0-9]{4}$/D'],
        ];
    }

    protected function riderAddressMessages(): array
    {
        return [
            'region_code.required' => 'Choose your region.',
            'city_municipality_code.required' => 'Choose your city or municipality.',
            'barangay_code.required' => 'Choose your barangay.',
            'line1.required' => 'Enter your house number and street.',
            'postal_code.required' => 'Enter your 4-digit postal code.',
            'postal_code.regex' => 'Enter your 4-digit postal code.',
        ];
    }

    protected function genderRules(): array
    {
        return ['gender' => ['required', Rule::in(array_keys(User::GENDERS))]];
    }

    /** Address fields ready for AddressBook::save(), with PSGC names (throws on a bad location). */
    public static function riderAddressData(array $validated, User $user, PsgcService $psgc): array
    {
        $location = [
            'region_code' => $validated['region_code'],
            'province_code' => $validated['province_code'] ?? null,
            'city_municipality_code' => $validated['city_municipality_code'],
            'barangay_code' => $validated['barangay_code'],
        ];

        return $location + $psgc->addressNames($location) + [
            'label' => 'Home',
            'recipient_name' => $user->name,
            'phone' => $user->phone ?? '',
            'line1' => $validated['line1'],
            'line2' => null,
            'postal_code' => $validated['postal_code'],
            'country_code' => 'PH',
            'is_default' => true,
        ];
    }
}
