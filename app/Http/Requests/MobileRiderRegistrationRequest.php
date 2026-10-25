<?php

namespace App\Http\Requests;

use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\User;
use App\Rules\PersonName;
use App\Rules\PhoneNumber;
use App\Rules\StrongPassword;
use App\Services\PsgcService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Rider sign-up from the mobile app: the website's rider rules (see AuthController::register) under the field
 * names the app uses, plus the home address and gender the app collects. Riders apply to one logistics partner,
 * which reviews them. With $withDocuments false (the pre-check before uploading) the two files are not required.
 */
class MobileRiderRegistrationRequest extends FormRequest
{
    public const TIMEZONE = 'Asia/Manila';

    protected bool $withDocuments = true;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = (string) $this->input('phone');
        // The app's vehicle keys are lower case ("motorcycle"); the website stores "Motorcycle".
        $vehicle = collect(Rider::VEHICLE_TYPES)->first(fn ($type) => strcasecmp($type, (string) $this->input('vehicle_type')) === 0);

        $this->merge([
            'phone' => preg_match('/^\+639[0-9]{9}$/D', $phone) ? '0'.substr($phone, 3) : $phone,
            'license_number' => Rider::normalizeLicense($this->input('license_number')) ?: null,
            'plate_number' => Rider::normalizePlate($this->input('plate_number')) ?: null,
            'vehicle_type' => $vehicle ?? $this->input('vehicle_type'),
        ]);
    }

    public function rules(): array
    {
        $licensed = fn (array $rules) => array_merge(['nullable', 'required_unless:vehicle_type,'.implode(',', Rider::UNLICENSED_VEHICLES)], $rules);
        $hasDocuments = Schema::hasColumn('riders', 'license_document_path');
        $document = ['file', 'mimes:jpg,jpeg,png,pdf', 'max:'.Rider::MAX_DOCUMENT_KB];

        return [
            'first_name' => ['required', 'string', 'min:2', 'max:50', new PersonName],
            'last_name' => ['required', 'string', 'min:2', 'max:50', new PersonName],
            'date_of_birth' => ['required', 'bail', 'date', 'before_or_equal:today', function (string $attribute, mixed $value, \Closure $fail) {
                if (Carbon::parse($value)->startOfDay()->gt(today()->subYears(18))) {
                    $fail('Riders must be at least 18 years old.');
                }
            }],
            'gender' => ['required', Rule::in(array_keys(User::GENDERS))],
            'email' => ['required', 'string', 'max:255', 'email:filter', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:11', new PhoneNumber, 'unique:users,phone'],
            'password' => ['required', 'string', 'confirmed', new StrongPassword],
            'logistics_provider_id' => ['required', 'integer', Rule::exists('logistics_providers', 'id')->where('status', 'approved')],
            'vehicle_type' => ['required', Rule::in(Rider::VEHICLE_TYPES)],
            'plate_number' => $licensed(['string', 'regex:'.Rider::PLATE_PATTERN, 'unique:riders,plate_no']),
            'license_number' => $licensed(['string', 'regex:'.Rider::LICENSE_PATTERN, 'unique:riders,license_number']),
            'license_expires_on' => $hasDocuments ? $licensed(['date', 'after_or_equal:today']) : ['nullable', 'date'],
            'license_image' => $this->withDocuments && $hasDocuments ? $licensed($document) : ['nullable'],
            'or_cr_image' => $this->withDocuments && $hasDocuments ? $licensed($document) : ['nullable'],
            'region_code' => ['required', 'regex:/^[0-9]{9}$/D'],
            'province_code' => ['nullable', 'regex:/^[0-9]{9}$/D'],
            'city_municipality_code' => ['required', 'regex:/^[0-9]{9}$/D'],
            'barangay_code' => ['required', 'regex:/^[0-9]{9}$/D'],
            'line1' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'regex:/^[0-9]{4}$/D'],
            'terms_accepted' => $this->withDocuments ? ['accepted'] : ['nullable'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $partner = $this->input('logistics_provider_id');
            if ($partner && ! $validator->errors()->has('logistics_provider_id') && ! LogisticsProvider::acceptingRiders()->whereKey($partner)->exists()) {
                $validator->errors()->add('logistics_provider_id', 'That logistics partner is not accepting riders right now.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'date_of_birth.before_or_equal' => 'Enter a valid date of birth. It cannot be a future date.',
            'gender.*' => 'Select your gender.',
            'email.email' => 'Enter a valid email address, such as name@example.com.',
            'email.unique' => 'This email address is already registered.',
            'phone.unique' => 'This mobile number is already registered.',
            'logistics_provider_id.required' => 'Choose the logistics partner you are applying to.',
            'logistics_provider_id.exists' => 'That logistics partner is not accepting riders right now.',
            'vehicle_type.required' => 'Select your vehicle type.',
            'vehicle_type.in' => 'Choose one of these vehicles: '.implode(', ', Rider::VEHICLE_TYPES).'.',
            'plate_number.required_unless' => 'Enter your vehicle plate number.',
            'plate_number.regex' => 'Enter the plate as 5 to 7 letters and numbers, for example 123ABC or NBC1234.',
            'plate_number.unique' => 'This plate number is already registered to another rider.',
            'license_number.required_unless' => "Enter your driver's license number.",
            'license_number.regex' => "Enter your driver's license number in the LTO format A00-00-000000.",
            'license_number.unique' => "This driver's license number is already registered.",
            'license_expires_on.required_unless' => "Enter your driver's license expiry date.",
            'license_expires_on.after_or_equal' => "Your driver's license has expired. Renew it before applying.",
            'license_image.required_unless' => "Upload a photo or PDF of your driver's license.",
            'or_cr_image.required_unless' => 'Upload a photo or PDF of your vehicle OR/CR.',
            'license_image.mimes' => "Driver's license: upload a JPG, PNG or PDF file.",
            'or_cr_image.mimes' => 'Vehicle OR/CR: upload a JPG, PNG or PDF file.',
            'license_image.max' => "Driver's license: the file must be 5 MB or smaller.",
            'or_cr_image.max' => 'Vehicle OR/CR: the file must be 5 MB or smaller.',
            'region_code.required' => 'Choose your region.',
            'city_municipality_code.required' => 'Choose your city or municipality.',
            'barangay_code.required' => 'Choose your barangay.',
            'line1.required' => 'Enter your house number and street.',
            'postal_code.required' => 'Enter your 4-digit postal code.',
            'postal_code.regex' => 'Enter your 4-digit postal code.',
            'terms_accepted.accepted' => 'Read and accept the Rider Terms and Conditions to submit your application.',
        ];
    }

    /** The home address, with location names looked up (throws a validation error for a location that does not exist). */
    public function homeAddress(PsgcService $psgc): array
    {
        $location = [
            'region_code' => $this->validated('region_code'),
            'province_code' => $this->validated('province_code'),
            'city_municipality_code' => $this->validated('city_municipality_code'),
            'barangay_code' => $this->validated('barangay_code'),
        ];

        return $location + $psgc->addressNames($location) + [
            'label' => 'Home',
            'line1' => $this->validated('line1'),
            'line2' => null,
            'postal_code' => $this->validated('postal_code'),
            'country_code' => 'PH',
        ];
    }
}
