<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Services\AddressBook;
use App\Services\PsgcService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Rider account settings in the app. Name, email, license and vehicle were checked by the logistics partner
 * when it approved the rider, so changing those goes through support; the rider can update their mobile number,
 * home address, gender and password.
 */
class MobileRiderAccountController extends Controller
{
    public function updatePhone(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'phone' => ['bail', 'required', 'string', 'digits:11', 'starts_with:09', Rule::unique('users', 'phone')->ignore($user->id)],
        ], [
            'phone.digits' => 'Your mobile number must be exactly 11 digits.',
            'phone.starts_with' => 'Your mobile number must start with 09.',
            'phone.unique' => 'This mobile number is already registered.',
        ]);

        $user->update(['phone' => $validated['phone']]);

        return response()->json(['message' => 'Mobile number updated.', 'phone' => $user->phone]);
    }

    public function updateGender(Request $request): JsonResponse
    {
        $validated = $request->validate(['gender' => ['required', Rule::in(array_keys(User::GENDERS))]], ['gender.*' => 'Select your gender.']);
        abort_unless(Schema::hasColumn('users', 'gender'), 503, 'Gender can be saved after the latest database update.');
        $request->user()->update(['gender' => $validated['gender']]);

        return response()->json(['message' => 'Gender updated.', 'gender' => $validated['gender']]);
    }

    /** Replaces the rider's home address (their default saved address). */
    public function updateAddress(Request $request, PsgcService $psgc, AddressBook $addresses): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'region_code' => ['required', 'regex:/^[0-9]{9}$/D'],
            'province_code' => ['nullable', 'regex:/^[0-9]{9}$/D'],
            'city_municipality_code' => ['required', 'regex:/^[0-9]{9}$/D'],
            'barangay_code' => ['required', 'regex:/^[0-9]{9}$/D'],
            'line1' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'regex:/^[0-9]{4}$/D'],
        ], [
            'region_code.required' => 'Choose your region.',
            'city_municipality_code.required' => 'Choose your city or municipality.',
            'barangay_code.required' => 'Choose your barangay.',
            'line1.required' => 'Enter your house number and street.',
            'postal_code.*' => 'Enter your 4-digit postal code.',
        ]);
        $location = collect($validated)->only(['region_code', 'province_code', 'city_municipality_code', 'barangay_code'])->all() + ['province_code' => null];
        $current = $user->addresses()->where('is_default', true)->first() ?? $user->addresses()->first();
        $address = $addresses->save($user, $location + $psgc->addressNames($location) + [
            'label' => $current?->label ?? 'Home',
            'recipient_name' => $user->name,
            'phone' => $user->phone ?? '',
            'line1' => $validated['line1'],
            'line2' => null,
            'postal_code' => $validated['postal_code'],
            'country_code' => 'PH',
            'is_default' => true,
        ], $current);

        return response()->json(['message' => 'Address updated.', 'address' => self::addressPayload($address)]);
    }

    public static function addressPayload(?Address $address): ?array
    {
        if (! $address) {
            return null;
        }

        return [
            'region_code' => $address->region_code,
            'region_name' => $address->region_name,
            'province_code' => $address->province_code,
            'province' => $address->province,
            'city_municipality_code' => $address->city_municipality_code,
            'city' => $address->city,
            'barangay_code' => $address->barangay_code,
            'barangay' => $address->barangay,
            'line1' => $address->line1,
            'postal_code' => $address->postal_code,
            'formatted' => $address->formatted(),
        ];
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:mobile'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', new StrongPassword],
        ], [
            'current_password.current_password' => 'Your current password is incorrect.',
            'password.different' => 'Choose a password different from your current one.',
        ]);

        $user->forceFill(['password' => $validated['password'], 'remember_token' => Str::random(60)])->save();

        // Sign out every other device; this phone stays signed in.
        $user->tokens()->whereKeyNot($user->currentAccessToken()?->getKey())->delete();

        return response()->json(['message' => 'Password updated. Other devices were signed out.']);
    }
}
