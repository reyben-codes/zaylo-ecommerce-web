<?php

namespace App\Models;

use App\Support\OrderParties;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A buyer's complaint about an order, reviewed by the admin with the buyer, seller and courier.
 */
class Dispute extends Model
{
    public const CATEGORIES = [
        'not_received' => 'Order not received',
        'damaged' => 'Damaged or defective item',
        'wrong_item' => 'Wrong item or variation',
        'not_as_described' => 'Not as described',
        'courier' => 'Courier or delivery problem',
        'refund' => 'Refund or payment problem',
        'other' => 'Something else',
    ];

    public const STATUSES = [
        'open' => 'Open',
        'in_review' => 'In review',
        'resolved' => 'Resolved',
        'rejected' => 'Rejected',
    ];

    public const MAX_EVIDENCE = 4;

    public const MAX_EVIDENCE_KB = 5120;

    protected $fillable = ['order_id', 'opened_by', 'resolved_by', 'status', 'reason', 'resolution', 'category', 'evidence', 'resolved_at'];

    protected $casts = ['evidence' => 'array', 'resolved_at' => 'datetime'];

    private ?array $partiesCache = null;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DisputeMessage::class)->orderBy('id');
    }

    /** Cases where the user is the buyer, seller or courier of the order. */
    public function scopeInvolving(Builder $query, User $user): Builder
    {
        return $query->whereHas('order', function (Builder $order) use ($user) {
            if (Order::usesLegacySchema()) {
                $order->where('user_id', $user->id)->orWhere('seller_id', $user->id)
                    ->orWhereHas('shipment', fn (Builder $shipment) => $shipment->where('courier_id', $user->id));

                return;
            }
            $order->where('buyer_id', $user->id)
                ->orWhereHas('sellerOrders.seller', fn (Builder $seller) => $seller->where('user_id', $user->id))
                ->orWhereHas('sellerOrders.shipment.rider', fn (Builder $rider) => $rider->where('user_id', $user->id));
        });
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['resolved', 'rejected'], true);
    }

    /** @return array{buyer: ?int, seller: ?int, courier: ?int} */
    public function parties(): array
    {
        return $this->partiesCache ??= OrderParties::for($this->order);
    }

    /** The admin, and the buyer, seller and courier of the order. */
    public function isOpenTo(User $user): bool
    {
        return $user->hasRole('admin') || in_array((int) $user->id, array_filter($this->parties()), true);
    }

    /** How a participant is labelled in the thread. */
    public function roleOf(?int $userId): string
    {
        $role = array_search($userId, $this->parties(), true);

        return $role ? ucfirst($role) : 'Zaylo admin';
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Something else';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
