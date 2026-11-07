<?php

namespace App\Models;

use App\Services\RiderDeliveries;
use App\Services\ShipmentWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Shipment extends Model
{
    protected $fillable = [
        'order_id', 'courier_id', 'pickup_courier_id', 'tracking_number', 'picked_up_at', 'delivered_at',
        'seller_order_id', 'logistics_provider_id', 'rider_id', 'tracking_code',
        'status', 'fee_minor', 'cod_amount_minor', 'cod_collected', 'attempts',
        'pickup_confirmed_at', 'pickup_scheduled_for', 'sorted_at', 'sort_area',
        'delivery_proof_path', 'handover_confirmed_at',
        'rider_latitude', 'rider_longitude', 'rider_located_at',
    ];

    protected $casts = [
        'cod_collected' => 'boolean', 'picked_up_at' => 'datetime', 'delivered_at' => 'datetime',
        'rider_located_at' => 'datetime',
        'pickup_confirmed_at' => 'datetime', 'pickup_scheduled_for' => 'date', 'sorted_at' => 'datetime',
        'handover_confirmed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** The first-mile courier who picks up parcels from the seller and brings them to the hub. */
    public function pickupCourier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pickup_courier_id');
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LogisticsProvider::class, 'logistics_provider_id');
    }

    /** Whether parcels record their logistics partner (the newer schema, or the deployed one after 2026_10_24). */
    public static function tracksProvider(): bool
    {
        return Schema::hasColumn('shipments', 'logistics_provider_id');
    }

    /** Whether parcels keep the rider's photo at the door (after the 2026_10_27_000002 migration). */
    public static function tracksDeliveryProof(): bool
    {
        return Schema::hasColumn('shipments', 'delivery_proof_path');
    }

    /** Whether sellers can confirm they handed a parcel to the logistics partner (same migration). */
    public static function tracksHandover(): bool
    {
        return Schema::hasColumn('shipments', 'handover_confirmed_at');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    /** What the rider earned for delivering this parcel (App\Services\RiderWallet). */
    public function riderCommission(): HasOne
    {
        return $this->hasOne(RiderCommission::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class)->orderBy('occurred_at');
    }

    /** What riders did with this parcel (accepted, declined, failed attempts, delivered), oldest first. */
    public function riderLogs(): HasMany
    {
        return $this->hasMany(RiderDeliveryLog::class)->orderBy('created_at')->orderBy('id');
    }

    /** The rider–buyer chat for this delivery, oldest first. */
    public function messages(): HasMany
    {
        return $this->hasMany(DeliveryMessage::class)->orderBy('created_at')->orderBy('id');
    }

    /** The assigned rider's user account, on either database schema. */
    public function riderUserId(): ?int
    {
        $userId = Schema::hasColumn('shipments', 'rider_id') ? $this->rider?->user_id : $this->courier_id;

        return $userId === null ? null : (int) $userId;
    }

    /** The rider and buyer can message each other while the parcel is with the rider. */
    public function chatIsOpen(): bool
    {
        return Schema::hasTable('delivery_messages')
            && $this->riderUserId() !== null
            && in_array(ShipmentWorkflow::stageOf($this), RiderDeliveries::ACTIVE_STAGES, true);
    }

    /**
     * Adds a message; a repeated [$clientId] returns the existing one, so a send retried after a dropped connection
     * is not stored twice.
     */
    /** [$attachment] is the stored photo or video from ChatMedia::store, if any. */
    public function postMessage(User $sender, string $body, bool $fromRider, ?string $clientId = null, array $attachment = []): DeliveryMessage
    {
        return $this->messages()->firstOrCreate(
            ['client_id' => $clientId ?: (string) Str::uuid()],
            ['sender_id' => $sender->id, 'from_rider' => $fromRider, 'body' => trim($body)] + $attachment,
        );
    }

    /** Marks the other side's messages as read by the [$rider] side. */
    public function markMessagesReadBy(bool $rider): void
    {
        $this->messages()->where('from_rider', ! $rider)->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function unreadMessagesFor(bool $rider): int
    {
        return Schema::hasTable('delivery_messages')
            ? $this->messages()->where('from_rider', ! $rider)->whereNull('read_at')->count()
            : 0;
    }
}
