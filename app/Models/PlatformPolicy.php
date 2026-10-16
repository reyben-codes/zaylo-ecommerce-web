<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * Policy text the admin can edit (terms, privacy, seller and delivery rules).
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

    protected $fillable = ['slug', 'title', 'body', 'updated_by'];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function forSlug(string $slug): ?self
    {
        return Schema::hasTable('platform_policies') ? static::where('slug', $slug)->first() : null;
    }
}
