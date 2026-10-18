<?php

namespace App\Services;

use App\Models\LogisticsProvider;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerApplication;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Shop, business, pickup address and document rules shared by seller sign-up and the seller application page.
 */
class SellerApplicationService
{
    public function __construct(private PsgcService $psgc) {}

    public function available(): bool
    {
        return Schema::hasTable('seller_applications');
    }

    /** Validation rules. Documents already on file may be kept when resubmitting. */
    public function rules(Request $request, ?SellerApplication $existing = null): array
    {
        $type = $request->input('seller_type');
        $required = SellerApplication::REQUIRED_DOCUMENTS[$type] ?? ['government_id'];
        $ignoreUser = $existing?->user_id;

        $rules = [
            'shop_name' => ['required', 'string', 'min:3', 'max:30', function (string $attribute, mixed $value, \Closure $fail) use ($ignoreUser) {
                if (is_string($value) && $this->shopNameTaken($value, $ignoreUser)) {
                    $fail('This shop name is already taken.');
                }
            }],
            'shop_category' => ['required', Rule::in(array_keys(SellerApplication::categories()))],
            'seller_type' => ['required', Rule::in(array_keys(SellerApplication::SELLER_TYPES))],
            'tin' => ['required', 'string', 'regex:/^[0-9]{3}-[0-9]{3}-[0-9]{3}-[0-9]{3}$/D',
                Rule::unique('seller_applications', 'tin')->ignore($existing?->id), function (string $attribute, mixed $value, \Closure $fail) {
                    // One TIN is one business, whether it sells or delivers.
                    if (Schema::hasColumn('logistics_providers', 'tin') && LogisticsProvider::where('tin', $value)->exists()) {
                        $fail('This TIN is already registered.');
                    }
                }],
            'region_code' => ['required', 'regex:/^[0-9]{9}$/D'],
            'province_code' => ['nullable', 'regex:/^[0-9]{9}$/D'],
            'city_code' => ['required', 'regex:/^[0-9]{9}$/D'],
            'barangay_code' => ['required', 'regex:/^[0-9]{9}$/D'],
            'street_address' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'regex:/^[0-9]{4}$/D'],
        ];

        foreach (array_keys(SellerApplication::DOCUMENTS) as $document) {
            if (! in_array($document, $required, true)) {
                $rules[$document] = ['exclude'];
                continue;
            }
            $onFile = $existing && $existing->{$document.'_path'};
            $rules[$document] = [$onFile ? 'nullable' : 'required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.SellerApplication::MAX_DOCUMENT_KB];
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = [
            'shop_name.min' => 'The shop name must be 3 to 30 characters.',
            'shop_name.max' => 'The shop name must be 3 to 30 characters.',
            'shop_category.required' => 'Choose your shop category.',
            'seller_type.required' => 'Choose your seller type.',
            'tin.regex' => 'Enter the TIN as 000-000-000-000 (12 digits).',
            'tin.unique' => 'This TIN is already registered.',
            'region_code.required' => 'Choose the region of your pickup address.',
            'city_code.required' => 'Choose the city or municipality of your pickup address.',
            'barangay_code.required' => 'Choose the barangay of your pickup address.',
            'street_address.required' => 'Enter the house, unit or lot number and street.',
            'postal_code.required' => 'Enter a 4-digit postal code.',
            'postal_code.regex' => 'Enter a 4-digit postal code.',
            'government_id.required' => 'Upload a government-issued ID.',
            'dti_certificate.required' => 'The DTI Certificate is required for sole proprietorships.',
            'sec_certificate.required' => 'The SEC Certificate is required for corporations.',
            'bir_2303.required' => 'BIR Form 2303 is required for sole proprietorships and corporations.',
        ];
        foreach (SellerApplication::DOCUMENTS as $field => $label) {
            $messages["{$field}.mimes"] = "{$label}: upload a JPG, PNG or PDF file.";
            $messages["{$field}.file"] = "{$label}: upload a JPG, PNG or PDF file.";
            $messages["{$field}.uploaded"] = "{$label}: the file could not be uploaded. Files must be 5 MB or smaller.";
            $messages["{$field}.max"] = "{$label}: the file must be 5 MB or smaller.";
        }

        return $messages;
    }

    /**
     * Place names come from PSGC, never from the browser. Call before saving anything.
     */
    public function addressNames(array $data): array
    {
        try {
            $names = $this->psgc->addressNames([
                'region_code' => $data['region_code'],
                'province_code' => $data['province_code'] ?? null,
                'city_municipality_code' => $data['city_code'],
                'barangay_code' => $data['barangay_code'],
            ]);
        } catch (ServiceUnavailableHttpException $exception) {
            throw ValidationException::withMessages(['region_code' => $exception->getMessage()]);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['barangay_code' => 'Choose a valid pickup location: each choice must belong to the one before it.']);
        }

        return [
            'region_name' => $names['region_name'],
            'province_name' => $names['province'] ?: null,
            'city_name' => $names['city'],
            'barangay_name' => $names['barangay'],
        ];
    }

    /**
     * Save a new or resubmitted application and its documents; the shop takes the application's name.
     * $storedDocuments (field => path) are files already on the local disk, e.g. from a completed sign-up.
     */
    public function save(User $user, ?Request $request, array $data, array $names, ?SellerApplication $existing = null, array $storedDocuments = []): SellerApplication
    {
        $application = $existing ?? new SellerApplication(['user_id' => $user->id]);
        $replaced = [];
        foreach (array_keys(SellerApplication::DOCUMENTS) as $document) {
            $column = $document.'_path';
            if (isset($storedDocuments[$document])) {
                $replaced[] = $application->{$column};
                $application->{$column} = $storedDocuments[$document];
            } elseif ($request?->hasFile($document) && array_key_exists($document, $data)) {
                $replaced[] = $application->{$column};
                $application->{$column} = $request->file($document)->store("seller-applications/{$user->id}", 'local');
            }
        }

        $application->fill([
            'shop_name' => trim($data['shop_name']),
            'shop_category' => $data['shop_category'],
            'seller_type' => $data['seller_type'],
            'tin' => $data['tin'],
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
            'reviewed_by' => null,
        ] + $names)->save();

        $this->renameShop($user, $application->shop_name);
        foreach (array_filter($replaced) as $path) {
            Storage::disk('local')->delete($path);
        }

        return $application;
    }

    public function shopNameTaken(string $name, ?int $ignoreUserId = null): bool
    {
        $name = mb_strtolower(trim($name));
        $others = fn ($query) => $ignoreUserId ? $query->where('user_id', '!=', $ignoreUserId) : $query;

        if ($this->available() && $others(SellerApplication::whereRaw('LOWER(shop_name) = ?', [$name]))->exists()) {
            return true;
        }

        return Product::usesLegacySchema()
            ? $others(SellerProfile::whereRaw('LOWER(store_name) = ?', [$name]))->exists()
            : $others(Seller::whereRaw('LOWER(name) = ?', [$name]))->exists();
    }

    private function renameShop(User $user, string $name): void
    {
        if (Product::usesLegacySchema()) {
            SellerProfile::updateOrCreate(['user_id' => $user->id], ['store_name' => $name]);

            return;
        }

        $user->sellerShop()->update(['name' => $name]);
    }
}
