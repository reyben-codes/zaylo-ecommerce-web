<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Policy text the admin can edit (terms, privacy, seller and delivery rules).
 *
 * Every page also ships built in (resources/policies/{slug}.txt, same "## " / "- " format as the admin
 * editor), so all of them are published from day one. An admin-written version replaces the built-in text.
 * The built-in wording is a starting point to review with legal counsel, not legal advice.
 */
class PlatformPolicy extends Model
{
    public const PAGES = [
        'terms' => 'Terms of Service',
        'privacy' => 'Privacy Policy',
        'seller' => 'Seller Policy',
        'prohibited' => 'Prohibited Items',
        'returns' => 'Returns and Refunds',
    ];

    /** The date the built-in text last changed. */
    public const BUILT_IN_UPDATED = '2026-10-24';

    protected $fillable = ['slug', 'title', 'body', 'updated_by'];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function forSlug(string $slug): ?self
    {
        return Schema::hasTable('platform_policies') ? static::where('slug', $slug)->first() : null;
    }

    /** What visitors see: the admin's version, else the built-in text, else nothing (not published). */
    public static function published(string $slug): ?self
    {
        return static::forSlug($slug) ?? static::builtIn($slug);
    }

    /** The built-in text as an unsaved policy, or null for pages without one. */
    public static function builtIn(string $slug): ?self
    {
        $path = resource_path("policies/{$slug}.txt");
        if (! isset(self::PAGES[$slug]) || ! is_file($path)) {
            return null;
        }

        $policy = new static([
            'slug' => $slug,
            'title' => self::PAGES[$slug],
            'body' => trim(file_get_contents($path)),
        ]);
        $policy->updated_at = Carbon::parse(self::BUILT_IN_UPDATED, 'Asia/Manila');

        return $policy;
    }
}
