<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RiderAddressRules;
use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rider sign-up from the mobile app (multipart: two document files plus
 * acceptance of the Rider Terms). It follows the website's rider rules: the
 * rider applies to a logistics partner, and bicycles need no license, plate
 * or OR/CR. Field names match the app's form so errors land on the right
 * input; every rule uses `bail` so each field reports only its first problem.
 */
class RegisterRiderRequest extends FormRequest
{
    use RiderAddressRules;

    public const MINIMUM_AGE = 18;

    /** Dates such as "expired yesterday" are judged in the riders' own timezone. */
    public const TIMEZONE = 'Asia/Manila';

    /** Rider names: letters (accents and ñ included) and spaces; no numbers or symbols. */
    public const NAME_MIN_LENGTH = 2;
    public const NAME_MAX_LENGTH = 50;
    private const NAME_PATTERN = 'regex:/^\p{L}[\p{L}\p{M} ]*$/uD';

    /** name@domain.tld with no spaces; the `email` rule then checks RFC details. */
    private const EMAIL_PATTERN = 'regex:/^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/D';

    /** LTO format: a letter, then 2-2-6 digits. */
    public const LICENSE_FORMAT = 'A00-00-000000';

    /** Documents: JPG, PNG, or PDF up to 5.0 MB (5120 KB). */
    public const DOCUMENT_TYPES = 'jpg,jpeg,png,pdf';
    public const DOCUMENT_MAX_KB = Rider::MAX_DOCUMENT_KB;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Accepts the license typed without dashes (N0123456789) and plates with spaces or dashes.
        $this->merge(array_filter([
            'license_number' => Rider::normalizeLicense($this->input('license_number')) ?: null,
            'plate_number' => Rider::normalizePlate($this->input('plate_number')) ?: null,
        ], fn ($value) => $value !== null));
    }

    public function rules(): array
    {
        return $this->detailRules() + [
            'license_image' => $this->licensedOnly($this->documentRules()),
            'or_cr_image' => $this->licensedOnly($this->documentRules()),
            // Step 3: the rider must have opened and read the Rider Terms.
            'terms_accepted' => ['accepted'],
        ];
    }

    /** Everything except the two uploads; also used to pre-check before uploading. */
    protected function detailRules(): array
    {
        $today = now(self::TIMEZONE)->toDateString();

        return [
            // Step 1: personal details
            'first_name' => ['bail', 'required', 'string', 'min:'.self::NAME_MIN_LENGTH, 'max:'.self::NAME_MAX_LENGTH, self::NAME_PATTERN],
            'last_name' => ['bail', 'required', 'string', 'min:'.self::NAME_MIN_LENGTH, 'max:'.self::NAME_MAX_LENGTH, self::NAME_PATTERN],
            'date_of_birth' => ['bail', 'required', 'date', 'before_or_equal:'.now(self::TIMEZONE)->subYears(self::MINIMUM_AGE)->toDateString()],
        ] + $this->genderRules() + $this->riderAddressRules() + [

            // Step 2: rider details
            'email' => ['bail', 'required', 'string', 'max:255', self::EMAIL_PATTERN, 'email', 'unique:users,email'],
            'phone' => ['bail', 'required', 'string', 'digits:11', 'starts_with:09', 'unique:users,phone'],
            'password' => ['required', 'string', 'confirmed', new StrongPassword],
            'logistics_provider_id' => ['bail', 'required', 'integer', function (string $attribute, mixed $value, \Closure $fail) {
                if (! LogisticsProvider::acceptingRiders()->whereKey($value)->exists()) {
                    $fail('That logistics partner is not accepting riders right now.');
                }
            }],
            'vehicle_type' => ['required', Rule::in(Rider::VEHICLE_TYPES)],
            'license_number' => $this->licensedOnly(['string', 'regex:'.Rider::LICENSE_PATTERN, 'unique:riders,license_number']),
            'license_expires_on' => $this->licensedOnly(['date', 'after_or_equal:'.$today]),
            'plate_number' => $this->licensedOnly(['string', 'regex:'.Rider::PLATE_PATTERN, 'unique:riders,plate_no']),
        ];
    }

    /** Required for motor vehicles; bicycles leave these empty. */
    private function licensedOnly(array $rules): array
    {
        return array_merge(['bail', 'nullable', 'required_unless:vehicle_type,'.implode(',', Rider::UNLICENSED_VEHICLES)], $rules);
    }

    private function documentRules(): array
    {
        return ['file', 'mimes:'.self::DOCUMENT_TYPES, 'max:'.self::DOCUMENT_MAX_KB];
    }

    public function messages(): array
    {
        $documents = [];
        foreach (['license_image' => "driver's license", 'or_cr_image' => 'vehicle OR/CR'] as $field => $label) {
            $documents += [
                "$field.required_unless" => "Upload a photo or PDF of your $label.",
                "$field.uploaded" => "The $label file could not be uploaded. It must be 5.0 MB or smaller.",
                "$field.mimes" => 'Only JPG, PNG, or PDF files can be uploaded.',
                "$field.max" => "The $label file must be 5.0 MB or smaller.",
            ];
        }

        return [
            'first_name.regex' => 'Use letters and spaces only in your first name.',
            'last_name.regex' => 'Use letters and spaces only in your last name.',
            'first_name.min' => 'Use at least '.self::NAME_MIN_LENGTH.' characters in your first name.',
            'last_name.min' => 'Use at least '.self::NAME_MIN_LENGTH.' characters in your last name.',
            'first_name.max' => 'Use '.self::NAME_MAX_LENGTH.' characters or fewer in your first name.',
            'last_name.max' => 'Use '.self::NAME_MAX_LENGTH.' characters or fewer in your last name.',
            'date_of_birth.before_or_equal' => 'Riders must be at least '.self::MINIMUM_AGE.' years old.',

            'email.regex' => 'Enter a valid email address, like name@example.com, without spaces.',
            'email.email' => 'Enter a valid email address, like name@example.com, without spaces.',
            'email.unique' => 'This email address is already registered.',

            'phone.digits' => 'Your mobile number must be exactly 11 digits.',
            'phone.starts_with' => 'Your mobile number must start with 09.',
            'phone.unique' => 'This mobile number is already registered.',

            'logistics_provider_id.required' => 'Choose the logistics partner you are applying to.',
            'vehicle_type.required' => 'Select your vehicle type.',
            'vehicle_type.in' => 'Select your vehicle type.',

            'license_number.required_unless' => "Enter your driver's license number.",
            'license_number.regex' => 'Enter your license number in the format '.self::LICENSE_FORMAT.', starting with a letter.',
            'license_number.unique' => 'This license number is already registered.',
            'license_expires_on.required_unless' => "Enter your driver's license expiry date.",
            'license_expires_on.after_or_equal' => 'Your driver\'s license has expired. Renew it before registering.',

            'plate_number.required_unless' => 'Enter your vehicle plate number.',
            'plate_number.regex' => 'Enter the plate as 5 to 7 letters and numbers, for example 123ABC or NBC1234.',
            'plate_number.unique' => 'This plate number is already registered.',

            'gender.required' => 'Select your gender.',
            'gender.in' => 'Select your gender.',

            'terms_accepted.accepted' => 'Read and accept the Rider Terms and Conditions to submit your application.',
        ] + $this->riderAddressMessages() + $documents;
    }
}
