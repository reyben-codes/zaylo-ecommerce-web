<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\LazyCollection;

/**
 * A notice from the admin for the chosen audience, shown in the announcement section at the top of their pages, as a
 * pop-up, by email, or any mix of these.
 */
class Announcement extends Model
{
    public const AUDIENCES = [
        'all' => 'Everyone (public)',
        'buyer' => 'Buyers only',
        'seller' => 'Sellers only',
        'logistics' => 'Logistics partners only',
        'rider' => 'Riders only',
    ];

    /** Who sees an announcement for each audience, shown beside the choice on the admin form. */
    public const AUDIENCE_HINTS = [
        'all' => 'Every buyer, seller, logistics partner and rider, plus visitors who are not signed in.',
        'buyer' => 'Shown on the shop, cart, orders and account pages of buyers.',
        'seller' => 'Shown across the Seller Center.',
        'logistics' => 'Shown across the logistics partner portal.',
        'rider' => 'Shown on the rider dashboard and delivery pages.',
    ];

    public const AUDIENCE_ICONS = [
        'all' => 'fa-globe', 'buyer' => 'fa-bag-shopping', 'seller' => 'fa-store', 'logistics' => 'fa-warehouse', 'rider' => 'fa-motorcycle',
    ];

    /** The ways to show an announcement: field => label, explanation and icon on the admin form. */
    public const CHANNELS = [
        'show_in_section' => ['Announcement section', 'A bar at the top of their pages, until they dismiss it or it ends.', 'fa-bars-staggered'],
        'show_as_modal' => ['Pop-up', 'Opens over their screen once, on the website and in the app.', 'fa-window-restore'],
        'send_email' => ['Email', 'Sent once to active, verified accounts. Visitors who are not signed in get none.', 'fa-envelope'],
    ];

    protected $fillable = ['title', 'body', 'audience', 'show_in_section', 'show_as_modal', 'send_email', 'is_published', 'starts_at', 'ends_at', 'created_by'];

    protected $casts = [
        'show_in_section' => 'boolean', 'show_as_modal' => 'boolean', 'send_email' => 'boolean',
        'is_published' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'emailed_at' => 'datetime',
    ];

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

    /** Live announcements in a role's announcement section (guests see the ones for everyone). */
    public static function for(?string $role): Collection
    {
        return static::shownVia('show_in_section', $role);
    }

    /** Live announcements that pop up for a role (guests see the ones for everyone). */
    public static function popupsFor(?string $role): Collection
    {
        return static::shownVia('show_as_modal', $role);
    }

    private static function shownVia(string $channel, ?string $role): Collection
    {
        if (! Schema::hasColumn('announcements', $channel)) {
            return new Collection();
        }
        $role = $role === 'courier' ? 'rider' : $role;

        return static::live()->where($channel, true)->whereIn('audience', array_values(array_filter(['all', $role])))->latest()->limit(3)->get();
    }

    /** The active, verified accounts in this announcement's audience, for email. */
    public function emailRecipients(): LazyCollection
    {
        $roles = $this->audience === 'all' ? ['buyer', 'seller', 'logistics', 'rider', 'courier']
            : ($this->audience === 'rider' ? ['rider', 'courier'] : [$this->audience]);

        return User::where('status', 'active')->whereNotNull('email_verified_at')
            ->where(fn (Builder $query) => Schema::hasColumn('users', 'role')
                ? $query->whereIn('role', $roles)
                : $query->whereHas('roles', fn (Builder $role) => $role->whereIn('name', $roles)))
            ->lazyById()->filter(fn (User $user) => $user->isActive());
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
