<?php

namespace App\Models;

use App\Services\RiderDeliveries;
use App\Services\ShipmentWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Shipment extends Model
{
    protected $fillable = [
        'order_id', 'courier_id', 'tracking_number', 'picked_up_at', 'delivered_at',
        'seller_order_id', 'logistics_provider_id', 'rider_id', 'tracking_code',
        'status', 'fee_minor', 'cod_amount_minor', 'cod_collected', 'attempts',
        'pickup_confirmed_at', 'pickup_scheduled_for', 'sorted_at', 'sort_area',
    ];

    protected $casts = [
        'cod_collected' => 'boolean', 'picked_up_at' => 'datetime', 'delivered_at' => 'datetime',
        'pickup_confirmed_at' => 'datetime', 'pickup_scheduled_for' => 'date', 'sorted_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
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
    public function postMessage(User $sender, string $body, bool $fromRider, ?string $clientId = null): DeliveryMessage
    {
        return $this->messages()->firstOrCreate(
            ['client_id' => $clientId ?: (string) Str::uuid()],
            ['sender_id' => $sender->id, 'from_rider' => $fromRider, 'body' => trim($body)],
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
