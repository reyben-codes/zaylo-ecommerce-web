<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticsProvider extends Model
{
    /** Uploaded with the application: field => label. JPG, PNG or PDF up to 5 MB each. */
    public const DOCUMENTS = [
        'sec_certificate' => 'SEC Certificate',
        'bir_2303' => 'BIR Form 2303',
        'business_permit' => "Mayor's / Business Permit",
        'representative_id' => 'Valid ID of Authorized Representative',
    ];

    public const MAX_DOCUMENT_KB = 5120;

    /** SEC registration number: 2 letters followed by 9 digits, e.g. CS202012345. */
    public const SEC_PATTERN = '/^[A-Z]{2}[0-9]{9}$/D';

    protected $fillable = [
        'user_id', 'name', 'slug', 'status', 'rejection_reason', 'contact_phone', 'service_area', 'approved_at',
        'sec_registration_no', 'tin', 'contact_position', 'service_areas',
        'region_code', 'region_name', 'province_code', 'province_name', 'city_code', 'city_name', 'barangay_code', 'barangay_name',
        'street_address', 'postal_code', 'sec_certificate_path', 'bir_2303_path', 'business_permit_path', 'representative_id_path',
        'submitted_at', 'reviewed_at',
    ];

    protected $casts = ['approved_at' => 'datetime', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'service_areas' => 'array'];

    /** Regions a partner can serve, as shown to riders and admins. */
    public static function serviceRegions(): array
    {
        return config('marketplace.service_regions');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function riders(): HasMany
    {
        return $this->hasMany(Rider::class);
    }

    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /** True once the partner has submitted business details (partners from before applications have none). */
    public function hasApplication(): bool
    {
        return filled($this->sec_registration_no);
    }

    /** The documents on file, as field => storage path. */
    public function documents(): array
    {
        return collect(array_keys(self::DOCUMENTS))
            ->mapWithKeys(fn ($field) => [$field => $this->{$field.'_path'}])
            ->filter()->all();
    }

    public function getFormattedAddressAttribute(): string
    {
        return collect([$this->street_address, $this->barangay_name, $this->city_name, $this->province_name, $this->region_name, $this->postal_code])
            ->filter()->implode(', ');
    }

    /**
     * Partners riders may apply to: approved by an administrator and owned by an active account.
     */
    public function scopeAcceptingRiders($query)
    {
        return $query->where('status', 'approved')
            ->whereHas('owner', fn ($owner) => $owner->where('status', 'active'))
            ->orderBy('name');
    }
}
