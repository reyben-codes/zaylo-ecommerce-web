<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class Order extends Model
{
    /** Legacy courier labels retained for older orders; new delivery partners are selected by sellers. */
    public const COURIERS = [
        'zaylo_express' => ['name' => 'ZAYLO Express', 'title' => 'ZAYLO Express (Standard Delivery)', 'description' => 'Official ZAYLO courier truck · 2–4 business days · Door-to-door barcode scan'],
        'jnt_express' => ['name' => 'J&T Express', 'title' => 'J&T Express Delivery', 'description' => 'J&T delivery truck pickup & drop · 1–3 business days · Real-time tracking scan'],
        'flash_express' => ['name' => 'Flash Express', 'title' => 'Flash Express', 'description' => 'Speed delivery truck · 1–2 business days · Priority door scan'],
    ];

    public const DEFAULT_COURIER = 'zaylo_express';

    protected $fillable = [
        'order_number', 'user_id', 'seller_id', 'status', 'subtotal', 'shipping_fee',
        'total', 'currency', 'recipient_name', 'phone', 'notes', 'placed_at', 'completed_at', 'delivered_at', 'shipping_discount',
        'buyer_id', 'reference', 'total_minor', 'payment_method', 'payment_status',
        'shipping_address', 'note', 'voucher_code', 'shipping_discount_minor', 'courier_choice', 'logistics_provider_id', 'shipping_zone',
        'delivery_latitude', 'delivery_longitude', 'voucher_discount', 'settlement_breakdown',
    ];

    public function courierName(): string
    {
        if (! static::usesLegacySchema()) {
            $names = $this->sellerOrders()->with('shipment.provider')->get()
                ->map(fn ($sellerOrder) => $sellerOrder->shipment?->provider?->name)->filter()->unique();
            if ($names->isNotEmpty()) {
                return $names->implode(', ');
            }
        }
        $partner = $this->deliveryShipment()?->provider ?? $this->logisticsProvider;
        if ($partner) {
            return $partner->name;
        }
        if (! $this->courier_choice) {
            return 'Logistics partner chosen by seller';
        }
        return (self::COURIERS[$this->courier_choice] ?? self::COURIERS[self::DEFAULT_COURIER])['name'];
    }

    protected function casts(): array
    {
        return static::usesLegacySchema()
            ? ['subtotal' => 'decimal:2', 'shipping_fee' => 'decimal:2', 'total' => 'decimal:2', 'placed_at' => 'datetime', 'completed_at' => 'datetime', 'delivered_at' => 'datetime', 'settlement_breakdown' => 'array']
            : ['shipping_address' => 'array', 'settlement_breakdown' => 'array', 'placed_at' => 'datetime', 'completed_at' => 'datetime', 'delivered_at' => 'datetime'];
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

    /** The registered logistics partner selected by the seller for this order. */
    public function logisticsProvider(): BelongsTo
    {
        return $this->belongsTo(LogisticsProvider::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function shipment()
    {
        return static::usesLegacySchema()
            ? $this->hasOne(Shipment::class)
            : $this->hasOneThrough(Shipment::class, SellerOrder::class);
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
    public function getUserIdAttribute(mixed $value): ?int { return static::usesLegacySchema() ? $value : $this->buyer_id; }
    public function getSellerIdAttribute(mixed $value): ?int { return static::usesLegacySchema() ? $value : $this->sellerOrders->first()?->seller_id; }
    public function getSubtotalAttribute(mixed $value): float { return static::usesLegacySchema() ? (float) $value : $this->sellerOrders->sum('subtotal_minor') / 100; }
    public function getShippingFeeAttribute(mixed $value): float { return static::usesLegacySchema() ? (float) $value : $this->sellerOrders->sum('shipping_fee_minor') / 100; }

    public function getStatusAttribute(mixed $value): string
    {
        if (static::usesLegacySchema()) return (string) $value;
        $statuses = $this->sellerOrders->pluck('status');
        if ($statuses->isEmpty()) return $value ?? 'placed';
        $active = $statuses->reject(fn ($status) => $status === 'cancelled');
        if ($active->isEmpty()) return 'cancelled';
        // Multi-seller historical orders show their earliest unfinished stage.
        foreach (['pending' => 'placed', 'accepted' => 'confirmed', 'packed' => 'processing', 'ready_to_ship' => 'ready_for_pickup', 'shipped' => 'in_transit', 'delivered' => 'delivered'] as $stored => $label) {
            if ($active->contains($stored)) return $label;
        }

        return 'completed';
    }

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
