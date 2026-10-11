<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RiderAddressRules;
use App\Models\RiderApplication;
use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rider sign-up from the mobile app (multipart: two document files plus
 * acceptance of the Rider Terms).
 * Field names match the app's form so errors land on the right input; every
 * rule uses `bail` so each field reports only its first problem.
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
    private const LICENSE_PATTERN = 'regex:/^[A-Z][0-9]{2}-[0-9]{2}-[0-9]{6}$/D';

    /** Six letters and digits with at least one of each, e.g. ABC123. */
    private const PLATE_PATTERN = 'regex:/^(?=[A-Z0-9]*[A-Z])(?=[A-Z0-9]*[0-9])[A-Z0-9]{6}$/D';

    /** Documents: JPG, PNG, or PDF up to 5.0 MB (5120 KB). */
    public const DOCUMENT_TYPES = 'jpg,jpeg,png,pdf';
    public const DOCUMENT_MAX_KB = 5120;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $license = $this->input('license_number');
        if (is_string($license)) {
            $license = strtoupper(preg_replace('/\s+/', '', $license));
            // Accept the number typed without dashes, e.g. N0123456789.
            if (preg_match('/^([A-Z][0-9]{2})([0-9]{2})([0-9]{6})$/D', $license, $parts)) {
                $license = "{$parts[1]}-{$parts[2]}-{$parts[3]}";
            }
            $this->merge(['license_number' => $license]);
        }

        $plate = $this->input('plate_number');
        if (is_string($plate)) {
            $this->merge(['plate_number' => strtoupper(preg_replace('/[\s-]+/', '', $plate))]);
        }
    }

    public function rules(): array
    {
        return $this->detailRules() + [
            'license_image' => $this->documentRules(),
            'or_cr_image' => $this->documentRules(),
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
            'license_number' => ['bail', 'required', 'string', self::LICENSE_PATTERN, 'unique:rider_applications,license_number'],
            'license_expires_on' => ['bail', 'required', 'date', 'after_or_equal:'.$today],
            'vehicle_type' => ['required', Rule::in(array_keys(RiderApplication::VEHICLE_TYPES))],
            'plate_number' => ['bail', 'required', 'string', 'size:6', self::PLATE_PATTERN, 'unique:rider_applications,plate_number'],
        ];
    }

    private function documentRules(): array
    {
        return ['bail', 'required', 'file', 'mimes:'.self::DOCUMENT_TYPES, 'max:'.self::DOCUMENT_MAX_KB];
    }

    public function messages(): array
    {
        $documents = [];
        foreach (['license_image' => "driver's license", 'or_cr_image' => 'vehicle OR/CR'] as $field => $label) {
            $documents += [
                "$field.required" => "Upload a photo or PDF of your $label.",
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

            'license_number.regex' => 'Enter your license number in the format '.self::LICENSE_FORMAT.', starting with a letter.',
            'license_number.unique' => 'This license number is already registered.',
            'license_expires_on.after_or_equal' => 'Your driver\'s license has expired. Renew it before registering.',

            'plate_number.size' => 'Your plate number must be exactly 6 characters.',
            'plate_number.regex' => 'Use letters and numbers only, with at least one of each (for example ABC123).',
            'plate_number.unique' => 'This plate number is already registered.',

            'gender.required' => 'Select your gender.',
            'gender.in' => 'Select your gender.',

            'terms_accepted.accepted' => 'Read and accept the Rider Terms and Conditions to submit your application.',
        ] + $this->riderAddressMessages() + $documents;
    }
}
