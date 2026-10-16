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
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    private ?string $pendingRole = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    /** Gender options offered at rider sign-up, keyed by stored value. */
    public const GENDERS = ['male' => 'Male', 'female' => 'Female'];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'date_of_birth',
        'gender',
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
            'identity_verified_at' => 'datetime',
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

    /**
     * The shop that owns this seller's products and orders, created on first use.
     */
    public function sellerShop(): Seller
    {
        return $this->sellers()->oldest('id')->firstOrCreate([], [
            'name' => $this->name."'s Store",
            'slug' => Str::slug($this->name).'-'.Str::lower(Str::random(6)),
            'status' => $this->status === 'active' ? 'approved' : 'pending',
        ]);
    }

    /**
     * Create the shop record for a new seller, in whichever table this database uses.
     */
    public function openSellerShop(string $name, string $status = 'pending'): void
    {
        if (Product::usesLegacySchema()) {
            SellerProfile::create(['user_id' => $this->id, 'store_name' => $name]);

            return;
        }

        $this->sellers()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'status' => $status,
        ]);
    }

    /**
     * The sign-in page for this account: partners have their own portals, everyone else uses the shop login.
     */
    public function portalLoginRoute(): string
    {
        return match (true) {
            $this->hasRole('seller') => 'seller.login',
            $this->hasRole('logistics') => 'logistics.login',
            $this->hasRole('admin') => 'admin.login',
            default => 'login',
        };
    }

    public function sellerStoreName(): string
    {
        if (Product::usesLegacySchema()) {
            $profile = $this->sellerProfile;

            return $profile?->store_name ?? $profile?->name ?? 'My Shop';
        }

        return $this->sellerShop()->name;
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

    /**
     * A seller whose application is under review or was rejected; they may only use the application page.
     */
    public function awaitsSellerApproval(): bool
    {
        return $this->hasRole('seller') && in_array($this->status, ['pending', 'rejected'], true)
            && Schema::hasTable('seller_applications');
    }

    public function sellerApplication(): HasOne
    {
        return $this->hasOne(SellerApplication::class);
    }

    public function identityVerifications(): HasMany
    {
        return $this->hasMany(IdentityVerification::class);
    }

    public function latestIdentityVerification(): HasOne
    {
        return $this->hasOne(IdentityVerification::class)->latestOfMany();
    }

    /**
     * Buyers need an approved ID to check out. Before the feature's migration runs, nobody is blocked.
     */
    public function isIdentityVerified(): bool
    {
        return ! Schema::hasColumn('users', 'identity_verified_at') || $this->identity_verified_at !== null;
    }

    public function logisticsProvider(): HasOne
    {
        return $this->hasOne(LogisticsProvider::class)->oldestOfMany();
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
