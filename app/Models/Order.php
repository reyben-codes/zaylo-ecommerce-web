<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'user_id', 'seller_id', 'status', 'subtotal', 'shipping_fee',
        'total', 'currency', 'recipient_name', 'phone', 'notes', 'placed_at', 'completed_at', 'delivered_at', 'shipping_discount',
        'buyer_id', 'reference', 'total_minor', 'payment_method', 'payment_status',
        'shipping_address', 'note', 'voucher_code', 'shipping_discount_minor', 'courier_choice',
    ];

    public function courierName(): string
    {
        return match ($this->courier_choice) {
            'jnt_express' => 'J&T Express',
            'flash_express' => 'Flash Express',
            default => 'ZAYLO Express',
        };
    }

    protected function casts(): array
    {
        return static::usesLegacySchema()
            ? ['subtotal' => 'decimal:2', 'shipping_fee' => 'decimal:2', 'total' => 'decimal:2', 'placed_at' => 'datetime', 'completed_at' => 'datetime', 'delivered_at' => 'datetime']
            : ['shipping_address' => 'array'];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, static::usesLegacySchema() ? 'user_id' : 'buyer_id');
    }

    public function items()
    {
        return static::usesLegacySchema()
            ? $this->hasMany(OrderItem::class)
            : $this->hasManyThrough(OrderItem::class, SellerOrder::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function shipment()
    {
        return $this->hasOne(Shipment::class);
    }

    /**
     * The order's parcel on either schema: the deployed one links shipments to orders; the newer one to the order's
     * seller orders (the parcel a rider carries, when there are several).
     */
    public function deliveryShipment(): ?Shipment
    {
        if (static::usesLegacySchema()) {
            return $this->shipment;
        }

        return Shipment::whereIn('seller_order_id', $this->sellerOrders()->select('id'))
            ->orderByRaw('rider_id IS NULL')->oldest('id')->first();
    }

    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    /** Complaints filed about this order. */
    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class)->latest();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getOrderNumberAttribute(mixed $value): string { return static::usesLegacySchema() ? (string) $value : (string) $this->reference; }
    public function getTotalAttribute(mixed $value): float { return static::usesLegacySchema() ? (float) $value : ((int) $this->total_minor / 100); }
    public function getShippingDiscountAttribute(mixed $value): float { return static::usesLegacySchema() ? (float) ($value ?? 0) : ((int) ($this->shipping_discount_minor ?? 0) / 100); }

    public function formattedShippingAddress(): string
    {
        $raw = $this->shipping_address;
        if (is_string($raw)) {
            return $raw;
        }
        if (is_array($raw)) {
            $parts = array_filter([
                $raw['address_line_1'] ?? $raw['street'] ?? $raw['address'] ?? null,
                $raw['barangay'] ?? null,
                $raw['city'] ?? null,
                $raw['province'] ?? null,
                $raw['postal_code'] ?? null,
            ]);

            return ! empty($parts) ? implode(', ', $parts) : ($raw['recipient'] ?? 'Buyer address');
        }

        return 'Buyer registered address';
    }

    public static function usesLegacySchema(): bool
    {
        return Schema::hasColumn('orders', 'user_id');
    }

    /** When the order reached the buyer's hands. */
    public function deliveredAt(): ?Carbon
    {
        if (static::usesLegacySchema()) {
            $at = $this->delivered_at ?? $this->shipment?->delivered_at;
            if ($at) {
                return $at instanceof \Carbon\CarbonInterface ? Carbon::instance($at) : Carbon::parse($at);
            }
            if (in_array($this->status, ['delivered', 'completed'], true)) {
                return $this->updated_at ? Carbon::instance($this->updated_at) : null;
            }

            return null;
        }

        $date = $this->sellerOrders()->whereNotNull('delivered_at')->max('delivered_at');
        if ($date) {
            return Carbon::parse($date);
        }
        if ($this->sellerOrders()->whereIn('status', ['delivered', 'completed'])->exists()) {
            return $this->updated_at ? Carbon::instance($this->updated_at) : null;
        }

        return null;
    }

    /**
     * Buyers can return damaged or wrong products within 24 hours of delivery.
     * After this deadline, physical returns and damaged/wrong claims are closed.
     */
    public function returnDeadline(): ?Carbon
    {
        $delivered = $this->deliveredAt();

        return $delivered ? $delivered->copy()->addHours(24) : null;
    }

    /** Whether the 24-hour return window for damaged or wrong products is currently active. */
    public function isWithinReturnWindow(): bool
    {
        $delivered = $this->deliveredAt();
        if (! $delivered) {
            return false;
        }

        return now()->lte($delivered->copy()->addHours(24));
    }

    /** Whether the 24-hour return window has ended. */
    public function isReturnWindowExpired(): bool
    {
        $delivered = $this->deliveredAt();
        if (! $delivered) {
            return false;
        }

        return now()->gt($delivered->copy()->addHours(24));
    }
}
