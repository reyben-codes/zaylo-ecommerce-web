<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerApplication extends Model
{
    public const SELLER_TYPES = [
        'individual' => 'Individual',
        'sole_proprietorship' => 'Sole Proprietorship',
        'corporation' => 'Corporation',
    ];

    /** Document field => label, accepted as JPG, PNG or PDF up to 5 MB each. */
    public const DOCUMENTS = [
        'government_id' => 'Government-issued ID',
        'dti_certificate' => 'DTI Certificate',
        'sec_certificate' => 'SEC Certificate',
        'bir_2303' => 'BIR Form 2303',
    ];

    /** Documents each seller type must provide. */
    public const REQUIRED_DOCUMENTS = [
        'individual' => ['government_id'],
        'sole_proprietorship' => ['government_id', 'dti_certificate', 'bir_2303'],
        'corporation' => ['government_id', 'sec_certificate', 'bir_2303'],
    ];

    public const MAX_DOCUMENT_KB = 5120;

    protected $fillable = [
        'user_id', 'shop_name', 'shop_category', 'seller_type', 'tin',
        'region_code', 'region_name', 'province_code', 'province_name', 'city_code', 'city_name',
        'barangay_code', 'barangay_name', 'street_address', 'postal_code',
        'government_id_path', 'dti_certificate_path', 'sec_certificate_path', 'bir_2303_path',
        'status', 'rejection_reason', 'submitted_at', 'reviewed_at', 'reviewed_by',
    ];

    protected $casts = ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];

    public static function categories(): array
    {
        return array_intersect_key(config('marketplace.categories'), array_flip([
            'fashion', 'electronics', 'home_living', 'beauty_health', 'groceries', 'sports_outdoors', 'toys_hobbies', 'automotive',
        ]));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requiredDocuments(): array
    {
        return self::REQUIRED_DOCUMENTS[$this->seller_type] ?? ['government_id'];
    }

    /** The documents on file for this seller type, as field => storage path. */
    public function documents(): array
    {
        $documents = [];
        foreach ($this->requiredDocuments() as $field) {
            if ($this->{$field.'_path'}) {
                $documents[$field] = $this->{$field.'_path'};
            }
        }

        return $documents;
    }

    public function getSellerTypeLabelAttribute(): string
    {
        return self::SELLER_TYPES[$this->seller_type] ?? $this->seller_type;
    }

    public function getCategoryLabelAttribute(): string
    {
        return config('marketplace.categories')[$this->shop_category] ?? $this->shop_category;
    }

    public function getFormattedAddressAttribute(): string
    {
        return collect([$this->street_address, $this->barangay_name, $this->city_name, $this->province_name, $this->region_name, $this->postal_code])
            ->filter()->implode(', ');
    }
}
