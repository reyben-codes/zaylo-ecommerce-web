<?php

namespace App\Support;

use App\Models\Dispute;

/**
 * The return of an item as one list of steps, the same on the website and in the app: when each step happened, its
 * proof photos, and what the person looking at it (buyer, seller, rider or logistics partner) does next.
 *
 * Steps follow App\Services\ReturnPickups: a pickup goes rider → hub → seller, a drop-off starts at the hub, and a
 * refund-only request has no item to send back. An exchange ends when the seller has the item.
 */
class ReturnProcess
{
    private const RANK = ['scheduled' => 0, 'picked_up' => 1, 'sorted' => 2, 'in_transit' => 3, 'received_by_seller' => 4, 'refunded' => 5];

    private const TIMEZONE = 'Asia/Manila';

    /**
     * @return array{
     *     steps: list<array{key: string, label: string, detail: ?string, at: ?\Carbon\CarbonInterface, photo: ?string, done: bool, current: bool}>,
     *     next: string, finished: bool
     * }
     */
    public static function for(Dispute $case, string $role): array
    {
        $rank = self::rank($case);
        $parcel = $case->hasReturnParcel();
        $pickup = $parcel && $case->return_method !== 'dropoff';
        $refunds = $case->return_type !== 'exchange';
        $refunded = $case->refunded_at !== null || $case->return_status === 'refunded';
        $at = fn ($time) => $time?->copy()->timezone(self::TIMEZONE);
        $rider = Dispute::tracksReturnRiders() ? $case->returnRider?->name : null;

        $steps = [self::step('requested', 'Return requested', 'Filed by the buyer', $at($case->created_at), true)];
        if ($parcel) {
            if ($pickup) {
                $steps[] = self::step('assigned', 'Rider assigned', $rider ? 'Rider: '.$rider : self::pickupWindow($case), $at($case->return_assigned_at ?? null), $rider !== null || $rank >= 1);
                $steps[] = self::step('picked_up', 'Collected from the buyer', 'The rider scanned the return slip', $at($case->return_picked_up_at ?? null), $rank >= 1, 'pickup');
            }
            $steps[] = self::step('sorted', $pickup ? 'At the logistics hub' : 'Dropped off at the hub', $pickup ? null : ($case->dropoff_location ?: 'Logistics hub'), $at($case->return_at_hub_at ?? null), $rank >= 2, 'hub');
            $steps[] = self::step('in_transit', 'On the way to the seller', null, null, $rank >= 3);
            $steps[] = self::step('received_by_seller', 'Seller received the item', null, $at($case->return_received_at ?? null), $rank >= 4);
        } else {
            $steps[] = self::step('review', 'Seller reviews the request', 'No item needs to be sent back', null, $case->status !== 'open' || $refunded);
        }
        if ($refunds) {
            $amount = $case->refund_amount !== null ? '₱'.number_format((float) $case->refund_amount, 2) : null;
            $steps[] = self::step('refunded', 'Refund issued', $amount, $at($case->refunded_at ?? null), $refunded);
        }

        // Photos only where they were taken.
        $photos = $case->returnPhotos();
        foreach ($steps as &$step) {
            $step['photo'] = isset($step['photo'], $photos[$step['photo']]) ? route('return-photo', [$case, $step['photo']]) : null;
        }
        unset($step);

        $current = collect($steps)->search(fn ($step) => ! $step['done']);
        if ($current !== false && $case->status !== 'rejected') {
            $steps[$current]['current'] = true;
        }
        $finished = $current === false || $case->status === 'rejected';

        return [
            'steps' => $steps,
            'next' => self::next($case, $role, $finished ? null : $steps[$current]),
            'finished' => $finished,
        ];
    }

    /** The same for the app: times as ISO strings. */
    public static function payload(Dispute $case, string $role): array
    {
        $process = self::for($case, $role);
        $process['steps'] = array_map(fn ($step) => ['at' => $step['at']?->toIso8601String()] + $step, $process['steps']);

        return $process;
    }

    private static function step(string $key, string $label, ?string $detail, $at, bool $done, ?string $photo = null): array
    {
        return compact('key', 'label', 'detail', 'at', 'photo', 'done') + ['current' => false];
    }

    private static function rank(Dispute $case): int
    {
        $rank = self::RANK[$case->return_status ?: 'scheduled'] ?? 0;
        if ($case->returnReceived()) {
            $rank = max($rank, 4);
        }

        return $case->refunded_at !== null ? 5 : $rank;
    }

    private static function pickupWindow(Dispute $case): string
    {
        return 'Pickup '.($case->pickup_date ? $case->pickup_date->format('D, M j') : 'date to be confirmed')
            .', '.($case->pickup_slot === 'afternoon' ? '1:00–6:00 PM' : '9:00 AM–1:00 PM');
    }

    /** What [$role] does now, given the first step not yet done ([$current], null when finished). */
    private static function next(Dispute $case, string $role, ?array $current): string
    {
        $step = $current['key'] ?? null;
        if ($case->status === 'rejected') {
            return 'This return was not approved, so nothing more needs to happen. The decision is in the case.';
        }
        if ($step === null) {
            return $case->refunded_at
                ? 'Done. A refund of ₱'.number_format((float) $case->refund_amount, 2).' was issued on '.$case->refunded_at->timezone(self::TIMEZONE)->format('M j, Y').'.'
                : ($case->return_type === 'exchange' ? 'Done. The seller has the item and arranges the replacement in the case.' : 'Done. The return is complete.');
        }
        $window = self::pickupWindow($case);
        $dropoff = ($case->dropoff_location ?: 'the logistics hub').($case->dropoff_deadline ? ' by '.$case->dropoff_deadline->format('M j, Y') : '');

        return match ($role) {
            'buyer' => match ($step) {
                'assigned', 'picked_up' => "Pack the item and keep the return slip ready. A rider collects it ({$window}) and scans the slip.",
                'sorted' => $case->return_method === 'dropoff' ? "Bring the item and the return slip to {$dropoff}." : 'Nothing to do. The rider is bringing the item to the logistics hub.',
                'in_transit', 'received_by_seller' => 'Nothing to do. Your item is on its way back to the seller.',
                'review' => 'The seller is reviewing your request. You can add details in the case.',
                default => 'The seller is checking the item and will issue your refund.',
            },
            'seller' => match ($step) {
                'in_transit' => 'The hub is sending the item back to you. Confirm on the Returns page when it arrives.',
                'received_by_seller' => 'The item is on its way to you. Press "Return received" on the Returns page when it arrives.',
                'review' => 'Review the request, then record the refund on the Returns page.',
                'refunded' => 'Check the item, then record the refund on the Returns page.',
                default => 'Nothing to do yet. The logistics partner brings the item back to you.',
            },
            'rider' => match ($step) {
                'assigned', 'picked_up' => "Collect the item ({$window}): scan the buyer's return slip and take a photo of the item.",
                'sorted' => 'Bring the item to your logistics hub. They scan it in.',
                default => 'Your part is done. The hub brings the item back to the seller.',
            },
            'logistics' => match ($step) {
                'assigned' => 'Give this pickup to a rider.',
                'picked_up' => 'Waiting for the rider to collect the item from the buyer.',
                'sorted' => 'When the item reaches the hub, scan it in and take a photo of it.',
                'in_transit' => 'Send the item back to the seller.',
                'received_by_seller' => 'Hand the item to the seller.',
                default => 'The item is back with the seller. The seller handles the refund.',
            },
            default => 'Next step: '.$current['label'].'.',
        };
    }
}
