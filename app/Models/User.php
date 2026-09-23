<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    private ?string $pendingRole = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'date_of_birth',
        'address',
        'status',
        'is_suspended',
        'approved_at',
        'approved_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'approved_at' => 'datetime',
            'is_suspended' => 'boolean',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            if (Schema::hasColumn('users', 'role') || ! Schema::hasTable('roles')) {
                return;
            }

            $roleName = $user->pendingRole ?? 'buyer';
            $role = Role::firstOrCreate(['name' => $roleName]);
            $user->roles()->syncWithoutDetaching([$role->id]);
        });
    }

    public function setRoleAttribute(?string $role): void
    {
        if (Schema::hasColumn('users', 'role')) {
            $this->attributes['role'] = $role;
        } else {
            $this->pendingRole = $role;
        }
    }

    public function getRoleAttribute(): string
    {
        if (array_key_exists('role', $this->attributes)) {
            return $this->attributes['role'] ?? 'buyer';
        }

        return $this->pendingRole
            ?? $this->getRelationValue('roles')?->first()?->name
            ?? $this->roles()->value('name')
            ?? 'buyer';
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string ...$roles): bool
    {
        if (Schema::hasColumn('users', 'role')) {
            return in_array($this->attributes['role'] ?? null, $roles, true);
        }

        return $this->roles()->whereIn('name', $roles)->exists();
    }

    public function sellers(): HasMany
    {
        return $this->hasMany(Seller::class);
    }

    public function sellerProfile(): HasOne
    {
        return Schema::hasTable('seller_profiles')
            ? $this->hasOne(SellerProfile::class)
            : $this->hasOne(Seller::class);
    }

    public function logisticsProviders(): HasMany
    {
        return $this->hasMany(LogisticsProvider::class);
    }

    public function rider(): HasOne
    {
        return $this->hasOne(Rider::class);
    }

    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, Schema::hasColumn('orders', 'user_id') ? 'user_id' : 'buyer_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function wishlistItems()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function isActive(): bool
    {
        return ! (bool) ($this->attributes['is_suspended'] ?? false) && $this->status === 'active';
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function sendEmailVerificationNotification(): void
    {
        app(\App\Services\EmailVerificationService::class)->send($this);
    }
}
