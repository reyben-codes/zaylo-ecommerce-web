<?php

namespace App\Services;

use App\Models\Dispute;
use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\DisputeUpdated;
use App\Support\ReturnProcess;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Bringing a returned item back to the seller. The logistics partner that delivered the order handles its return:
 *
 *  1. pickup returns: the partner assigns one of its riders, who scans the return label at the buyer's door
 *     ("picked_up"); drop-off returns skip this, since the buyer brings the item to the hub;
 *  2. the partner scans the item in at the hub ("sorted"), sends it out ("in_transit") and hands it to the seller
 *     ("received_by_seller"); the seller can also confirm it arrived;
 *  3. the seller records the refund (SellerReturnController).
 *
 * The rider photographs the item when collecting it and the partner when it arrives at the hub, as proof of what was
 * returned (Dispute::returnPhotos()).
 *
 * The buyer and seller are emailed at each step and see it on the case.
 */
class ReturnPickups
{
    /** Steps the logistics partner takes from each step, by return method. */
    private const LOGISTICS_STEPS = [
        'picked_up' => ['sorted'],
        'sorted' => ['in_transit'],
        'in_transit' => ['received_by_seller'],
    ];

    public const LOGISTICS_LABELS = [
        'sorted' => 'Received at the hub',
        'in_transit' => 'Sent back to the seller',
        'received_by_seller' => 'Handed to the seller',
    ];

    /** Returns whose item can still be scanned by its rider: waiting at the buyer's door, or on the way to the hub. */
    public const RIDER_STAGES = ['scheduled', 'picked_up'];

    public function __construct(private ChatPhotos $photos) {}

    public static function isPickup(Dispute $dispute): bool
    {
        return $dispute->hasReturnParcel() && $dispute->return_method !== 'dropoff';
    }

    /** The partner that delivered the order, which handles its return; null when none is recorded. */
    public static function providerId(Dispute $dispute): ?int
    {
        if (! Shipment::tracksProvider()) {
            return null;
        }
        $id = $dispute->order?->deliveryShipment()?->logistics_provider_id;

        return $id === null ? null : (int) $id;
    }

    /** What the logistics partner can do next with this return. */
    public static function logisticsSteps(Dispute $dispute): array
    {
        if (! $dispute->hasReturnParcel() || $dispute->status === 'rejected' || $dispute->returnReceived()) {
            return [];
        }
        $status = $dispute->return_status ?: 'scheduled';
        // A drop-off arrives at the hub with the buyer; a pickup only once its rider scanned it.
        if ($status === 'scheduled') {
            return self::isPickup($dispute) ? [] : ['sorted'];
        }

        return self::LOGISTICS_STEPS[$status] ?? [];
    }

    /** A pickup waiting at the buyer's door, which the partner can (re)assign to a rider. */
    public static function canAssign(Dispute $dispute): bool
    {
        return Dispute::tracksReturnRiders() && self::isPickup($dispute) && $dispute->status !== 'rejected'
            && in_array($dispute->return_status ?: 'scheduled', ['scheduled'], true);
    }

    /** Return parcels the partner handles, newest first. */
    public function forProvider(?LogisticsProvider $provider): Collection
    {
        return Dispute::query()->whereNotNull('return_tracking_number')
            ->with(['order.items', 'order.buyer', ...(Dispute::tracksReturnRiders() ? ['returnRider'] : [])])
            ->latest()->get()
            ->filter(fn (Dispute $dispute) => $provider === null || in_array(self::providerId($dispute), [null, $provider->id], true))
            ->values();
    }

    public function handledBy(Dispute $dispute, ?LogisticsProvider $provider): bool
    {
        return $provider === null || in_array(self::providerId($dispute), [null, $provider->id], true);
    }

    /** Returns assigned to the rider that still need them: to pick up, or to bring to the hub. */
    public function forRider(User $rider): Collection
    {
        if (! Dispute::tracksReturnRiders()) {
            return collect();
        }

        return Dispute::where('return_rider_id', $rider->id)->whereIn('return_status', self::RIDER_STAGES)
            ->where('status', '!=', 'rejected')
            ->with(['order.items', 'order.buyer'])->orderBy('pickup_date')->oldest('id')->get();
    }

    public function assign(Dispute $dispute, Rider $rider, User $actor): Dispute
    {
        if (! self::canAssign($dispute)) {
            throw ValidationException::withMessages(['rider_id' => 'Only pickup returns still at the buyer can be assigned to a rider.']);
        }
        $dispute->update(['return_rider_id' => $rider->user_id, 'return_assigned_at' => now()]);
        $this->note($dispute, 'Rider '.($rider->user?->name ?? '#'.$rider->id).' will pick up the returned item'
            .($dispute->pickup_date ? ' on '.$dispute->pickup_date->format('M j, Y') : '').'.', $actor);

        return $dispute;
    }

    /** The rider scanned the return label at the buyer's door, with a [$photo] of the item collected. */
    public function scan(User $rider, string $code, ?UploadedFile $photo = null): Dispute
    {
        $code = strtoupper(trim($code));
        $dispute = Dispute::tracksReturnRiders()
            ? Dispute::where('return_rider_id', $rider->id)->where('return_tracking_number', $code)->first()
            : null;
        if (! $dispute) {
            throw ValidationException::withMessages(['code' => 'This return is not assigned to you. Check the return slip, or ask your logistics partner.']);
        }

        if (($dispute->return_status ?: 'scheduled') !== 'scheduled' || $dispute->status === 'rejected') {
            throw ValidationException::withMessages(['code' => 'This return was already picked up. Bring it to your logistics hub.']);
        }
        $path = $this->proof($photo, $dispute, 'Take a photo of the returned item when you collect it from the buyer.');

        $this->saving($path, fn () => DB::transaction(function () use ($dispute, $rider, $path) {
            $locked = Dispute::whereKey($dispute->id)->lockForUpdate()->firstOrFail();
            if (($locked->return_status ?: 'scheduled') !== 'scheduled' || $locked->status === 'rejected') {
                throw ValidationException::withMessages(['code' => 'This return was already picked up. Bring it to your logistics hub.']);
            }
            $locked->update(['return_status' => 'picked_up', 'return_picked_up_at' => now()] + ($path ? ['return_pickup_photo_path' => $path] : []));
            $this->note($locked, 'The rider picked up the returned item from the buyer and is bringing it to the logistics hub.', $rider, notify: false);
        }));
        $dispute->refresh();
        $this->notify($dispute, $rider);

        return $dispute;
    }

    /**
     * The logistics partner moves the return on: in at the hub (with a [$photo] of the item as it arrived), sent out,
     * handed to the seller.
     */
    public function advance(Dispute $dispute, string $to, User $actor, ?UploadedFile $photo = null): Dispute
    {
        $path = $to === 'sorted' && in_array('sorted', self::logisticsSteps($dispute), true)
            ? $this->proof($photo, $dispute, 'Take a photo of the returned item as it arrived at the hub.')
            : null;

        $this->saving($path, fn () => DB::transaction(function () use ($dispute, $to, $actor, $path) {
            $locked = Dispute::whereKey($dispute->id)->lockForUpdate()->firstOrFail();
            if (! in_array($to, self::logisticsSteps($locked), true)) {
                throw ValidationException::withMessages(['return_status' => self::isPickup($locked) && ($locked->return_status ?: 'scheduled') === 'scheduled'
                    ? 'The rider has to scan this return at the buyer\'s door first.'
                    : 'This return can no longer move to "'.(Dispute::RETURN_STEPS[$to] ?? $to).'". Refresh and try again.']);
            }
            $locked->update(['return_status' => $to] + ($path ? ['return_hub_photo_path' => $path] : []) + match ($to) {
                'sorted' => Dispute::tracksReturnRiders() ? ['return_at_hub_at' => now()] : [],
                'received_by_seller' => Dispute::tracksRefunds() ? ['return_received_at' => now()] : [],
                default => [],
            });
            $this->note($locked, match ($to) {
                'sorted' => 'The returned item arrived at the logistics hub.',
                'in_transit' => 'The logistics partner is bringing the returned item back to the seller.',
                default => 'The logistics partner handed the returned item to the seller.',
            }, $actor, notify: false);
        }));
        $dispute->refresh();
        $this->notify($dispute, $actor);

        return $dispute;
    }

    /** Payload for the rider app. */
    public static function riderPayload(Dispute $dispute): array
    {
        $order = $dispute->order;
        $status = $dispute->return_status ?: 'scheduled';
        $process = ReturnProcess::payload($dispute, 'rider');

        return [
            'id' => $dispute->id,
            'tracking_number' => $dispute->return_tracking_number,
            'status' => $status,
            'status_label' => $dispute->returnStepLabel(),
            'order_number' => $order?->order_number,
            'buyer_name' => $order?->recipient_name ?? $order?->buyer?->name,
            'phone' => $order?->phone,
            'address' => $order?->formattedShippingAddress(),
            'pickup_date' => $dispute->pickup_date?->toDateString(),
            'pickup_slot' => $dispute->pickup_slot === 'afternoon' ? 'Afternoon, 1:00–6:00 PM' : 'Morning, 9:00 AM–1:00 PM',
            'items' => $order ? $order->items->map(fn ($item) => $item->quantity.' × '.$item->product_name)->values() : [],
            'reason' => $dispute->category_label,
            'can_scan' => $status === 'scheduled',
            // Collecting the item needs a photo of it, the proof of what the buyer returned.
            'needs_photo' => Dispute::tracksReturnPhotos(),
            'next_step' => $process['next'],
            'steps' => collect($process['steps'])->map(fn (array $step) => [
                'key' => $step['key'], 'label' => $step['label'], 'detail' => $step['detail'], 'at' => $step['at'],
                'done' => $step['done'], 'current' => $step['current'],
            ])->values(),
        ];
    }

    /** Stores the proof photo for a step; required once the photo columns exist. */
    private function proof(?UploadedFile $photo, Dispute $dispute, string $missing): ?string
    {
        if (! Dispute::tracksReturnPhotos()) {
            return null;
        }
        if (! $photo) {
            throw ValidationException::withMessages(['photo' => $missing]);
        }

        return $this->photos->store($photo, 'return-proof/'.$dispute->id);
    }

    /** Runs the step; a step that fails leaves no stray photo behind. */
    private function saving(?string $path, callable $step): void
    {
        try {
            $step();
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }
    }

    /** Saved in the case thread (from whoever took the step), so the buyer and seller see each step. */
    private function note(Dispute $dispute, string $body, User $actor, bool $notify = true): void
    {
        $dispute->messages()->create(['sender_id' => $actor->id, 'body' => 'Return update: '.$body]);
        if ($notify) {
            $this->notify($dispute, $actor);
        }
    }

    private function notify(Dispute $dispute, User $actor): void
    {
        $parties = $dispute->parties();
        $ids = array_values(array_filter([$parties['buyer'] ?? null, $parties['seller'] ?? null], fn ($id) => $id && $id !== (int) $actor->id));
        foreach (User::whereIn('id', $ids)->get() as $user) {
            try {
                $user->notify(new DisputeUpdated($dispute, 'return_step'));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }
}
