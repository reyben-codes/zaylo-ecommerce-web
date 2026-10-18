<?php

namespace App\Services;

use App\Models\LogisticsProvider;
use App\Models\SellerApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Company, business registration, office address, service regions and document rules shared by logistics sign-up
 * and the logistics application page (where a rejected partner corrects and resubmits).
 */
class LogisticsApplicationService
{
    public function __construct(private SellerApplicationService $sellerApplications) {}

    public function available(): bool
    {
        return Schema::hasColumn('logistics_providers', 'sec_registration_no');
    }

    /** SEC numbers are compared in capitals without spaces, so "cs 202012345" matches CS202012345. */
    public function prepare(Request $request): void
    {
        if (is_string($request->input('sec_registration_no'))) {
            $request->merge(['sec_registration_no' => strtoupper(preg_replace('/\s+/', '', $request->input('sec_registration_no')))]);
        }
    }

    /** Validation rules. Documents already on file may be kept when resubmitting. */
    public function rules(?LogisticsProvider $existing = null): array
    {
        $rules = [
            'company_name' => ['required', 'string', 'min:2', 'max:150', function (string $attribute, mixed $value, \Closure $fail) use ($existing) {
                if (is_string($value) && $this->companyNameTaken($value, $existing?->id)) {
                    $fail('This company name is already registered.');
                }
            }],
            'sec_registration_no' => ['required', 'string', 'regex:'.LogisticsProvider::SEC_PATTERN,
                Rule::unique('logistics_providers', 'sec_registration_no')->ignore($existing?->id)],
            'tin' => ['required', 'string', 'regex:/^[0-9]{3}-[0-9]{3}-[0-9]{3}-[0-9]{3}$/D',
                Rule::unique('logistics_providers', 'tin')->ignore($existing?->id), function (string $attribute, mixed $value, \Closure $fail) {
                    if ($this->sellerApplications->available() && SellerApplication::where('tin', $value)->exists()) {
                        $fail('This TIN is already registered.');
                    }
                }],
            'contact_position' => ['required', 'string', 'max:100'],
            'region_code' => ['required', 'regex:/^[0-9]{9}$/D'],
            'province_code' => ['nullable', 'regex:/^[0-9]{9}$/D'],
            'city_code' => ['required', 'regex:/^[0-9]{9}$/D'],
            'barangay_code' => ['required', 'regex:/^[0-9]{9}$/D'],
            'street_address' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'regex:/^[0-9]{4}$/D'],
            'service_areas' => ['required', 'array', 'min:1'],
            'service_areas.*' => ['required', 'string', Rule::in(array_keys(LogisticsProvider::serviceRegions()))],
        ];

        foreach (array_keys(LogisticsProvider::DOCUMENTS) as $document) {
            $onFile = $existing && $existing->{$document.'_path'};
            $rules[$document] = [$onFile ? 'nullable' : 'required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.LogisticsProvider::MAX_DOCUMENT_KB];
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = [
            'company_name.min' => 'The company name must be 2 to 150 characters.',
            'sec_registration_no.required' => 'Enter your SEC registration number.',
            'sec_registration_no.regex' => 'Enter the SEC registration number as 2 letters followed by 9 digits, for example CS202012345.',
            'sec_registration_no.unique' => 'This SEC registration number is already registered.',
            'tin.required' => 'Enter your company TIN.',
            'tin.regex' => 'Enter the TIN as 000-000-000-000 (12 digits).',
            'tin.unique' => 'This TIN is already registered.',
            'contact_position.required' => "Enter the contact person's position.",
            'region_code.required' => 'Choose the region of your office address.',
            'city_code.required' => 'Choose the city or municipality of your office address.',
            'barangay_code.required' => 'Choose the barangay of your office address.',
            'street_address.required' => 'Enter the house, unit or lot number and street.',
            'postal_code.required' => 'Enter a 4-digit postal code.',
            'postal_code.regex' => 'Enter a 4-digit postal code.',
            'service_areas.required' => 'Choose at least one region you deliver to.',
            'service_areas.min' => 'Choose at least one region you deliver to.',
            'service_areas.*.in' => 'Choose service areas from the list.',
            'service_areas.*.required' => 'Choose at least one region you deliver to.',
        ];
        foreach (LogisticsProvider::DOCUMENTS as $field => $label) {
            $messages["{$field}.required"] = "Upload your {$label}.";
            $messages["{$field}.mimes"] = "{$label}: upload a JPG, PNG or PDF file.";
            $messages["{$field}.file"] = "{$label}: upload a JPG, PNG or PDF file.";
            $messages["{$field}.uploaded"] = "{$label}: the file could not be uploaded. Files must be 5 MB or smaller.";
            $messages["{$field}.max"] = "{$label}: the file must be 5 MB or smaller.";
        }

        return $messages;
    }

    /** Place names come from PSGC, never from the browser. Call before saving anything. */
    public function addressNames(array $data): array
    {
        return $this->sellerApplications->addressNames($data);
    }

    /**
     * Save a new or resubmitted application and its documents.
     * $storedDocuments (field => path) are files already on the local disk, e.g. from a completed sign-up.
     */
    public function save(User $owner, ?Request $request, array $data, array $names, ?LogisticsProvider $existing = null, array $storedDocuments = []): LogisticsProvider
    {
        $provider = $existing ?? new LogisticsProvider([
            'user_id' => $owner->id,
            'slug' => Str::slug($data['company_name']).'-'.Str::lower(Str::random(6)),
        ]);
        $replaced = [];
        foreach (array_keys(LogisticsProvider::DOCUMENTS) as $document) {
            $column = $document.'_path';
            if (isset($storedDocuments[$document])) {
                $replaced[] = $provider->{$column};
                $provider->{$column} = $storedDocuments[$document];
            } elseif ($request?->hasFile($document) && array_key_exists($document, $data)) {
                $replaced[] = $provider->{$column};
                $provider->{$column} = $request->file($document)->store("logistics-applications/{$owner->id}", 'local');
            }
        }

        $regions = LogisticsProvider::serviceRegions();
        $areas = array_values(array_intersect(array_keys($regions), $data['service_areas']));
        $provider->fill([
            'name' => trim($data['company_name']),
            'sec_registration_no' => $data['sec_registration_no'],
            'tin' => $data['tin'],
            'contact_phone' => $owner->phone,
            'contact_position' => trim($data['contact_position']),
            'service_areas' => $areas,
            // Riders and admins read the regions as one line.
            'service_area' => collect($areas)->map(fn ($key) => $regions[$key])->implode('; '),
            'region_code' => $data['region_code'],
            'province_code' => $data['province_code'] ?? null,
            'city_code' => $data['city_code'],
            'barangay_code' => $data['barangay_code'],
            'street_address' => trim($data['street_address']),
            'postal_code' => $data['postal_code'],
            'status' => 'pending',
            'rejection_reason' => null,
            'submitted_at' => now(),
            'reviewed_at' => null,
        ] + $names)->save();

        foreach (array_filter($replaced) as $path) {
            Storage::disk('local')->delete($path);
        }

        return $provider;
    }

    public function companyNameTaken(string $name, ?int $ignoreProviderId = null): bool
    {
        return LogisticsProvider::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->when($ignoreProviderId, fn ($query) => $query->whereKeyNot($ignoreProviderId))
            ->exists();
    }
}
