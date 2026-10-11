<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * A rider's sign-up documents, reviewed by logistics staff before the rider
 * can sign in. Images live on the private `local` disk.
 *
 * Lifecycle: pending -> approved (account active, courier profile created)
 *                    -> rejected (account stays inactive, reason recorded).
 * Always change status through approve() / reject() so the application, the
 * user account, and the courier profile stay in step.
 */
class RiderApplication extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /** Vehicle types offered in the app, keyed by stored value. */
    public const VEHICLE_TYPES = [
        'motorcycle' => 'Motorcycle',
        'tricycle' => 'Tricycle',
        'car' => 'Car',
        'van' => 'Van',
    ];

    /** Bump when the Rider Terms text in the app changes (mobile_app/lib/rider/content/rider_terms.dart). */
    public const TERMS_VERSION = '2026-10-11';

    protected $fillable = [
        'user_id', 'license_number', 'license_expires_on', 'license_image_path',
        'vehicle_type', 'plate_number', 'or_cr_image_path', 'terms_accepted_at', 'terms_version',
        'status', 'rejection_reason', 'reviewed_at', 'reviewed_by',
    ];

    protected $casts = [
        'license_expires_on' => 'date',
        'reviewed_at' => 'datetime',
        'terms_accepted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The logistics staff member who approved or rejected the application. */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Applications waiting for review, oldest first. */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)->oldest();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Approves the application: activates the rider's account and creates (or
     * updates) their courier profile. The rider confirms their email with a
     * code the first time they sign in.
     */
    public function approve(?User $reviewer = null, ?string $notes = null): void
    {
        $this->ensurePending();

        DB::transaction(function () use ($reviewer, $notes) {
            $this->update([
                'status' => self::STATUS_APPROVED,
                'rejection_reason' => null,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer?->id,
            ]);

            $this->user->update([
                'status' => 'active',
                'approved_at' => now(),
                'approved_by' => $reviewer?->id,
            ]);

            CourierProfile::updateOrCreate(['user_id' => $this->user_id], [
                'vehicle_type' => $this->vehicle_type,
                'license_number' => $this->license_number,
                'verification_notes' => $notes,
            ]);
        });
    }

    /** Rejects the application; the rider's account stays inactive. */
    public function reject(string $reason, ?User $reviewer = null): void
    {
        $this->ensurePending();

        DB::transaction(function () use ($reason, $reviewer) {
            $this->update([
                'status' => self::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer?->id,
            ]);

            $this->user->update(['status' => 'suspended', 'approved_at' => null, 'approved_by' => null]);
        });
    }

    private function ensurePending(): void
    {
        if (! $this->isPending()) {
            throw new LogicException("Rider application #{$this->id} was already {$this->status}.");
        }
    }
}
