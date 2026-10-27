<?php

namespace App\Models;

use App\Support\OrderParties;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

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

    public const RETURN_TYPES = [
        'return_refund' => 'Return and Refund',
        'refund_only' => 'Refund Only',
        'exchange' => 'Replacement / Exchange',
    ];

    public const RETURN_METHODS = [
        'pickup' => 'Courier Pickup (Home Address)',
        'dropoff' => 'Drop-off (Courier Branch / Hub)',
    ];

    public const RETURN_COURIERS = [
        'zaylo_express' => 'ZAYLO Express',
        'jnt_express' => 'J&T Express',
        'flash_express' => 'Flash Express',
    ];

    public const MAX_EVIDENCE = 4;

    public const MAX_EVIDENCE_KB = 5120;

    protected $fillable = [
        'order_id', 'opened_by', 'resolved_by', 'status', 'reason', 'resolution',
        'category', 'evidence', 'resolved_at',
        'return_type', 'return_method', 'return_courier', 'pickup_date', 'pickup_slot',
        'dropoff_location', 'dropoff_deadline', 'return_tracking_number', 'return_status',
    ];

    protected $casts = [
        'evidence' => 'array',
        'resolved_at' => 'datetime',
        'pickup_date' => 'date',
        'dropoff_deadline' => 'date',
    ];

    public function returnCourierName(): string
    {
        return self::RETURN_COURIERS[$this->return_courier] ?? 'ZAYLO Express';
    }

    public function returnMethodName(): string
    {
        return self::RETURN_METHODS[$this->return_method] ?? 'Pickup';
    }

    private ?array $partiesCache = null;

    /** Validation for a buyer filing a complaint or return, on the website and in the app. */
    public static function filingRules(): array
    {
        return [
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'details' => ['required', 'string', 'min:20', 'max:2000'],
            'evidence' => ['nullable', 'array', 'max:'.self::MAX_EVIDENCE],
            'evidence.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.self::MAX_EVIDENCE_KB],
            'return_type' => ['nullable', Rule::in(array_keys(self::RETURN_TYPES))],
            'return_method' => ['nullable', Rule::in(array_keys(self::RETURN_METHODS))],
            'return_courier' => ['nullable', Rule::in(array_keys(self::RETURN_COURIERS))],
            'pickup_date' => ['nullable', 'date'],
            'pickup_slot' => ['nullable', 'string', 'max:50'],
            'dropoff_location' => ['nullable', 'string', 'max:255'],
        ];
    }

    public static function filingMessages(): array
    {
        return [
            'category.required' => 'Choose what went wrong.',
            'details.required' => 'Describe the problem.',
            'details.min' => 'Describe the problem in at least 20 characters so the team can act on it.',
            'evidence.max' => 'Attach up to '.self::MAX_EVIDENCE.' files.',
            'evidence.*.mimes' => 'Evidence must be photos (JPG, PNG, WebP) or PDF files.',
            'evidence.*.max' => 'Each file must be 5 MB or smaller.',
        ];
    }

    /** Validation for a reply in the case thread from the buyer, seller or rider. */
    public static function replyRules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.self::MAX_EVIDENCE_KB],
        ];
    }

    public static function replyMessages(): array
    {
        return ['body.required' => 'Write a reply first.', 'attachment.mimes' => 'Attach a photo (JPG, PNG, WebP) or a PDF.'];
    }

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
