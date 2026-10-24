<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** One thing an account did (see config/activity.php and App\Services\ActivityLogger). */
class ActivityLog extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'actor_name', 'actor_role', 'logistics_provider_id', 'event', 'category', 'description',
        'subject_type', 'subject_id', 'subject_label', 'properties', 'ip_address', 'user_agent',
    ];

    protected $casts = ['properties' => 'array'];

    // Request fields shown with an entry, in this order, with readable names.
    private const DETAIL_LABELS = [
        'status' => 'Status', 'decision' => 'Decision', 'action' => 'Action', 'available' => 'Available',
        'stock' => 'Stock', 'area' => 'Area', 'pickup_date' => 'Pickup date', 'reason' => 'Reason',
        'slug' => 'Page', 'report' => 'Report', 'document' => 'Document', 'code' => 'Parcel',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(max(1, (int) config('activity.retention_days', 365))));
    }

    public function getCategoryLabelAttribute(): string
    {
        return config('activity.categories.'.$this->category) ?? Str::headline($this->category);
    }

    public function getIconAttribute(): string
    {
        return match ($this->event) {
            'auth.failed' => 'fa-triangle-exclamation',
            'logout' => 'fa-right-from-bracket',
            default => match ($this->category) {
                'auth' => 'fa-right-to-bracket',
                'account' => 'fa-user-gear',
                'orders' => 'fa-box',
                'products' => 'fa-shirt',
                'disputes' => 'fa-scale-balanced',
                'deliveries' => 'fa-motorcycle',
                'logistics' => 'fa-layer-group',
                'riders' => 'fa-people-group',
                'users' => 'fa-users-gear',
                'marketplace' => 'fa-shield-halved',
                'finance' => 'fa-percent',
                'settings' => 'fa-sliders',
                'documents' => 'fa-file-lines',
                default => 'fa-circle-dot',
            },
        };
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->actor_role) {
            'logistics' => 'Logistics partner',
            'admin' => 'Administrator',
            null => '—',
            default => Str::headline($this->actor_role),
        };
    }

    /** The request fields kept with the entry, as label => readable value. */
    public function details(): array
    {
        $properties = $this->properties ?? [];
        $details = [];
        foreach (self::DETAIL_LABELS as $key => $label) {
            if (! array_key_exists($key, $properties)) {
                continue;
            }
            $value = $properties[$key];
            $details[$label] = match (true) {
                $key === 'available' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No',
                in_array($key, ['status', 'decision', 'action', 'report', 'slug'], true) => Str::headline((string) $value),
                default => (string) $value,
            };
        }

        return $details;
    }

    /** The controller's confirmation message, e.g. "Parcel TRK-1 sorted for Pasig City.". */
    public function getResultAttribute(): ?string
    {
        return $this->properties['result'] ?? null;
    }

    /** A short, readable name for what was acted on, kept on the entry in case the record is deleted later. */
    public static function labelFor(Model $subject): string
    {
        return Str::limit(match (true) {
            $subject instanceof Order => 'Order '.($subject->order_number ?? $subject->reference ?? '#'.$subject->getKey()),
            $subject instanceof SellerOrder => 'Order #'.$subject->getKey(),
            $subject instanceof Shipment => 'Parcel '.($subject->tracking_number ?? $subject->tracking_code ?? '#'.$subject->getKey()),
            $subject instanceof Rider => 'Rider '.($subject->user?->name ?? '#'.$subject->getKey()),
            $subject instanceof Dispute => 'Complaint #'.$subject->getKey(),
            $subject instanceof IdentityVerification => 'ID of '.($subject->user?->name ?? 'buyer #'.$subject->user_id),
            $subject instanceof OrderItem => $subject->product_name ?? 'Order item #'.$subject->getKey(),
            $subject instanceof Address => trim(($subject->label ? $subject->label.' · ' : '').($subject->city ?? 'Address #'.$subject->getKey())),
            $subject instanceof ComplianceWarning => 'Warning #'.$subject->getKey(),
            $subject instanceof User => $subject->name,
            default => $subject->getAttribute('name')
                ?? $subject->getAttribute('title')
                ?? Str::headline(class_basename($subject)).' #'.$subject->getKey(),
        }, 250);
    }
}
