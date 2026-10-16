<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * A notice from the admin shown at the top of the pages of the chosen audience.
 */
class Announcement extends Model
{
    public const AUDIENCES = [
        'all' => 'Everyone',
        'buyer' => 'Buyers',
        'seller' => 'Sellers',
        'rider' => 'Riders',
        'logistics' => 'Logistics partners',
    ];

    protected $fillable = ['title', 'body', 'audience', 'is_published', 'starts_at', 'ends_at', 'created_by'];

    protected $casts = ['is_published' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /** Live announcements for a role (guests see the ones for everyone). */
    public static function for(?string $role): Collection
    {
        if (! Schema::hasTable('announcements')) {
            return new Collection();
        }
        $role = $role === 'courier' ? 'rider' : $role;

        return static::live()->whereIn('audience', array_values(array_filter(['all', $role])))->latest()->limit(3)->get();
    }

    public function getStateAttribute(): string
    {
        return match (true) {
            ! $this->is_published => 'draft',
            $this->starts_at && $this->starts_at->isFuture() => 'scheduled',
            $this->ends_at && $this->ends_at->isPast() => 'ended',
            default => 'live',
        };
    }
}
