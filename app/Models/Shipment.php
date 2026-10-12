<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Shipment extends Model
{
    /** Statuses in which the rider and buyer can message each other. */
    public const CHAT_OPEN_STATUSES = ['assigned', 'picked_up', 'in_transit'];

    protected $fillable = [
        'order_id', 'courier_id', 'tracking_number', 'picked_up_at', 'delivered_at',
        'seller_order_id', 'logistics_provider_id', 'rider_id', 'tracking_code',
        'status', 'fee_minor', 'cod_amount_minor', 'cod_collected', 'attempts',
    ];

    protected $casts = ['cod_collected' => 'boolean', 'picked_up_at' => 'datetime', 'delivered_at' => 'datetime'];

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

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class)->orderBy('occurred_at');
    }

    /** The rider–buyer chat for this delivery, oldest first. */
    public function messages(): HasMany
    {
        return $this->hasMany(DeliveryMessage::class)->orderBy('created_at')->orderBy('id');
    }

    /** A rider has accepted the delivery and it has not finished yet. */
    public function chatIsOpen(): bool
    {
        return $this->courier_id !== null && in_array($this->status, self::CHAT_OPEN_STATUSES, true);
    }

    /**
     * Adds a message; a repeated [$clientId] returns the existing one, so a
     * send retried after a dropped connection is not stored twice.
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
        return $this->messages()->where('from_rider', ! $rider)->whereNull('read_at')->count();
    }
}
