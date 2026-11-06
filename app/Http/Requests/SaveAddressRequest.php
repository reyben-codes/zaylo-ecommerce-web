<?php

namespace App\Http\Requests;

use App\Services\ParcelTracking;
use App\Services\PsgcService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        $address = $this->route('address');

        return $this->user() && (! $address || $address->user_id === $this->user()->id);
    }

    public function rules(): array
    {
        return [
            'recipient_name' => 'required|string|max:150',
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{7,30}$/D'],
            'region_code' => ['required', 'regex:/^[0-9]{9}$/D'],
            'province_code' => ['nullable', 'regex:/^[0-9]{9}$/D'],
            'city_municipality_code' => ['required', 'regex:/^[0-9]{9}$/D'],
            'barangay_code' => ['required', 'regex:/^[0-9]{9}$/D'],
            'line1' => 'required|string|max:255',
            'line2' => 'nullable|string|max:255',
            'postal_code' => ['required', 'regex:/^[0-9]{4}$/D'],
            'label' => ['required', Rule::in(['Home', 'Work', 'School', 'Other'])],
            'is_default' => 'sometimes|boolean',
            'return_to' => ['nullable', Rule::in(['checkout'])],
        ] + ParcelTracking::pinRules();
    }

    public function messages(): array
    {
        return [
            'latitude.between' => 'Pin a spot in the Philippines.',
            'longitude.between' => 'Pin a spot in the Philippines.',
        ];
    }

    public function addressData(PsgcService $psgc): array
    {
        $data = $this->safe()->except(['return_to']);
        $data['province_code'] = $data['province_code'] ?? null;
        $data['line2'] = $data['line2'] ?? null;
        $data['is_default'] = $this->boolean('is_default');
        if (! ParcelTracking::pinsAddresses()) {
            unset($data['latitude'], $data['longitude']);
        } elseif (array_key_exists('latitude', $data)) {
            $data = array_replace($data, ParcelTracking::point($data['latitude'], $data['longitude']) ?? ['latitude' => null, 'longitude' => null]);
        }

        return $data + $psgc->addressNames($data) + ['country_code' => 'PH'];
    }
}
