<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rider extends Model
{
    public const VEHICLE_TYPES = ['Motorcycle', 'Bicycle', 'Car', 'Van', 'Truck'];

    /** Bicycles need no driver's license, plate or OR/CR. */
    public const UNLICENSED_VEHICLES = ['Bicycle'];

    /** LTO driver's license number: a letter, then 2-2-6 digits. */
    public const LICENSE_PATTERN = '/^[A-Z][0-9]{2}-[0-9]{2}-[0-9]{6}$/D';

    /** Plate numbers: 5 to 7 letters and digits with at least one of each, e.g. 123ABC or NBC1234. */
    public const PLATE_PATTERN = '/^(?=[A-Z0-9]*[A-Z])(?=[A-Z0-9]*[0-9])[A-Z0-9]{5,7}$/D';

    /** Uploaded with the application: field => label. JPG, PNG or PDF up to 5 MB each. */
    public const DOCUMENTS = [
        'license_document' => "Driver's license",
        'or_cr_document' => 'Vehicle OR/CR',
    ];

    public const MAX_DOCUMENT_KB = 5120;

    protected $fillable = [
        'user_id', 'logistics_provider_id', 'vehicle_type', 'plate_no', 'license_number',
        'license_expires_on', 'license_document_path', 'or_cr_document_path',
        'is_active', 'status', 'rejection_reason', 'reviewed_at',
    ];

    protected $casts = ['is_active' => 'boolean', 'reviewed_at' => 'datetime', 'license_expires_on' => 'date'];

    /** Accepts the license typed in lower case or without dashes, e.g. n0123456789. */
    public static function normalizeLicense(?string $value): ?string
    {
        if (! is_string($value)) {
            return $value;
        }
        $value = strtoupper(preg_replace('/\s+/', '', $value));

        return preg_match('/^([A-Z][0-9]{2})-?([0-9]{2})-?([0-9]{6})$/D', $value, $parts)
            ? "{$parts[1]}-{$parts[2]}-{$parts[3]}"
            : $value;
    }

    public static function normalizePlate(?string $value): ?string
    {
        return is_string($value) ? strtoupper(preg_replace('/[\s-]+/', '', $value)) : $value;
    }

    public function getLicenseExpiredAttribute(): bool
    {
        return $this->license_expires_on !== null && $this->license_expires_on->lt(today());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LogisticsProvider::class, 'logistics_provider_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
