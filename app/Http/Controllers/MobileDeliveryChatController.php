<?php

namespace App\Http\Controllers;

use App\Models\DeliveryMessage;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ChatMedia;
use App\Services\ShipmentWorkflow;
use App\Support\OrderParties;
use App\Support\ParcelView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Chat between the rider delivering an order and the buyer who placed it.
 * The rider reaches it from the delivery, the buyer from the order. Messages
 * can be sent while the delivery is in progress; the thread stays readable
 * after it is delivered or cancelled.
 */
class MobileDeliveryChatController extends Controller
{
    public function __construct(private readonly ChatMedia $media) {}

    public function riderShow(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorizeRider($request, $shipment);

        return response()->json(['chat' => $this->thread($shipment, rider: true)]);
    }

    public function riderSend(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorizeRider($request, $shipment);

        return $this->send($request, $shipment, rider: true);
    }

    public function buyerShow(Request $request, Order $order): JsonResponse
    {
        return response()->json(['chat' => $this->thread($this->buyerShipment($request, $order), rider: false)]);
    }

    public function buyerSend(Request $request, Order $order): JsonResponse
    {
        return $this->send($request, $this->buyerShipment($request, $order), rider: false);
    }

    private function authorizeRider(Request $request, Shipment $shipment): void
    {
        abort_unless($shipment->riderUserId() === (int) $request->user()->id, 404, 'This delivery is not assigned to you.');
        abort_unless(Schema::hasTable('delivery_messages'), 503, 'Delivery chat is being set up. Please try again later.');
    }

    /** The order's delivery, once a rider is assigned to it. */
    private function buyerShipment(Request $request, Order $order): Shipment
    {
        abort_unless(OrderParties::for($order)['buyer'] === (int) $request->user()->id, 404);
        abort_unless(Schema::hasTable('delivery_messages'), 503, 'Delivery chat is being set up. Please try again later.');
        $shipment = $order->deliveryShipment();
        abort_unless($shipment?->riderUserId() !== null, 404, 'No rider has been assigned to this order yet.');

        return $shipment;
    }

    /** The rider's own copy of a photo or video in the delivery chat. */
    public function riderMedia(Request $request, Shipment $shipment, DeliveryMessage $message, string $kind)
    {
        $this->authorizeRider($request, $shipment);

        return $this->serveMedia($shipment, $message, $kind);
    }

    public function buyerMedia(Request $request, Order $order, DeliveryMessage $message, string $kind)
    {
        return $this->serveMedia($this->buyerShipment($request, $order), $message, $kind);
    }

    private function serveMedia(Shipment $shipment, DeliveryMessage $message, string $kind)
    {
        abort_unless((int) $message->shipment_id === (int) $shipment->id, 404);

        return ChatMedia::response($message, $kind);
    }

    private function send(Request $request, Shipment $shipment, bool $rider): JsonResponse
    {
        $canAttach = ChatMedia::supported('delivery_messages');
        $validated = $request->validate(
            ($canAttach ? ChatMedia::rules() : ['body' => ['required', 'string', 'max:2000']]) + ['client_id' => ['nullable', 'uuid']],
            ['body.required' => 'Type a message to send.'] + ChatMedia::messages(),
        );

        if (! $shipment->chatIsOpen()) {
            throw ValidationException::withMessages(['body' => $this->closedReason($shipment)]);
        }

        // A retried send with the same client id is stored once, so its file is not saved twice either.
        $retried = isset($validated['client_id']) && $shipment->messages()->where('client_id', $validated['client_id'])->exists();
        $attachment = $canAttach && ! $retried ? $this->media->store($request, "delivery-chat/{$shipment->id}") : [];
        $message = $shipment->postMessage($request->user(), (string) ($validated['body'] ?? ''), fromRider: $rider,
            clientId: $validated['client_id'] ?? null, attachment: $attachment);

        return response()->json(['message' => $this->messagePayload($message, $shipment, rider: $rider)], 201);
    }

    /** The whole thread, oldest first; opening it marks the other side's messages as read. */
    private function thread(Shipment $shipment, bool $rider): array
    {
        $shipment->loadMissing('order');
        $shipment->markMessagesReadBy($rider);
        $open = $shipment->chatIsOpen();

        return [
            'shipment_id' => $shipment->id,
            'order_number' => ($shipment->order ?? $shipment->sellerOrder?->order)?->order_number,
            'status' => ShipmentWorkflow::stageOf($shipment),
            'open' => $open,
            'closed_reason' => $open ? null : $this->closedReason($shipment),
            // Each side sees the other's first name only.
            'other_name' => $rider
                ? Str::of(str_replace('—', 'Buyer', ParcelView::for($shipment)['recipient']))->before(' ')->toString()
                : Str::of(User::find($shipment->riderUserId())?->name ?? 'Your rider')->before(' ')->toString(),
            'other_role' => $rider ? 'Buyer' : 'Rider',
            'messages' => $shipment->messages()->get()
                ->map(fn (DeliveryMessage $message) => $this->messagePayload($message, $shipment, rider: $rider))->values(),
        ];
    }

    private function closedReason(Shipment $shipment): string
    {
        return match (ShipmentWorkflow::stageOf($shipment)) {
            'delivered' => 'This order has been delivered, so the chat is closed.',
            'returned' => 'This parcel was returned to the seller, so the chat is closed.',
            'cancelled' => 'This delivery was cancelled, so the chat is closed.',
            default => 'Chat opens once a rider is assigned to the delivery.',
        };
    }

    private function messagePayload(DeliveryMessage $message, Shipment $shipment, bool $rider): array
    {
        // Each side opens the files through its own route: the rider by delivery, the buyer by order.
        $route = fn (string $kind) => $rider
            ? route('mobile.rider.deliveries.chat.media', [$shipment->id, $message, $kind])
            : route('mobile.orders.chat.media', [($shipment->order ?? $shipment->sellerOrder?->order)?->id, $message, $kind]);

        return [
            'id' => $message->id,
            'client_id' => $message->client_id,
            'body' => $message->body,
            'mine' => $message->from_rider === $rider,
            'read' => $message->read_at !== null,
            'sent_at' => $message->created_at?->toIso8601String(),
        ] + ChatMedia::links($message, $route);
    }
}
