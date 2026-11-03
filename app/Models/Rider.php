<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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

    /** Work days, in calendar order: key => label. */
    public const WORK_DAYS = [
        'mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun',
    ];

    protected $fillable = [
        'user_id', 'logistics_provider_id', 'vehicle_type', 'plate_no', 'license_number',
        'license_expires_on', 'license_document_path', 'or_cr_document_path',
        'is_active', 'status', 'rejection_reason', 'reviewed_at', 'shift_start', 'shift_end', 'work_days',
    ];

    protected $casts = ['is_active' => 'boolean', 'reviewed_at' => 'datetime', 'license_expires_on' => 'date', 'work_days' => 'array'];

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

    public function getHasScheduleAttribute(): bool
    {
        return $this->shift_start !== null && $this->shift_end !== null && ! empty($this->work_days);
    }

    /** Whether [$date] is one of the rider's work days. */
    public function worksOn(DateTimeInterface $date): bool
    {
        return $this->has_schedule && in_array(strtolower($date->format('D')), $this->work_days, true);
    }

    /** "Mon–Fri · 8:00 AM – 5:00 PM", or null without a schedule. */
    public function getScheduleLabelAttribute(): ?string
    {
        if (! $this->has_schedule) {
            return null;
        }

        return self::workDaysLabel($this->work_days).' · '.self::shiftLabel($this->shift_start, $this->shift_end);
    }

    /** Consecutive days collapse into a range: Mon–Fri, Sat; every day is "Daily". */
    public static function workDaysLabel(array $days): string
    {
        $keys = array_keys(self::WORK_DAYS);
        $picked = array_values(array_filter($keys, fn ($key) => in_array($key, $days, true)));
        if (count($picked) === 7) {
            return 'Daily';
        }
        $runs = [];
        foreach ($picked as $day) {
            $last = array_key_last($runs);
            if ($last !== null && array_search(end($runs[$last]), $keys, true) + 1 === array_search($day, $keys, true)) {
                $runs[$last][] = $day;
            } else {
                $runs[] = [$day];
            }
        }

        return collect($runs)->map(fn ($run) => count($run) > 2
            ? self::WORK_DAYS[$run[0]].'–'.self::WORK_DAYS[end($run)]
            : collect($run)->map(fn ($day) => self::WORK_DAYS[$day])->implode(', '))->implode(', ');
    }

    public static function shiftLabel(?string $start, ?string $end): string
    {
        $format = fn (?string $time) => $time ? Carbon::createFromFormat('H:i', substr($time, 0, 5))->format('g:i A') : '—';

        return $format($start).' – '.$format($end);
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

    public function attendances(): HasMany
    {
        return $this->hasMany(RiderAttendance::class);
    }
}
