<?php

namespace App\Observers;

use App\Models\DeliveryMessage;
use App\Models\LogisticsMessage;
use App\Models\Message;
use App\Models\OrderStatusHistory;
use App\Models\Rider;
use App\Models\Shipment;
use App\Models\SupportMessage;
use App\Services\MobilePushNotifications;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MobilePushObserver
{
    public function created(Model $model): void
    {
        $push = app(MobilePushNotifications::class);
        if ($model instanceof OrderStatusHistory) {
            $order = $model->order;
            $push->queue($order?->buyer?->id, 'order-history-'.$model->id, 'Order update',
                'Your order has an update. Open ZAYLO to view the details.', 'order', $order?->id ?? 0);
        } elseif ($model instanceof Message) {
            $conversation = $model->conversation;
            if ($conversation && (int) $model->sender_id !== (int) $conversation->buyer_id) {
                $push->queue($conversation->buyer_id, 'seller-message-'.$model->id, 'New seller message',
                    'You have a new message in ZAYLO.', 'seller_chat', $conversation->id);
            }
        } elseif ($model instanceof SupportMessage) {
            $recipient = $model->thread?->user_id;
            if ($recipient && (int) $model->sender_id !== (int) $recipient) {
                $push->queue($recipient, 'support-message-'.$model->id, 'ZAYLO support replied',
                    'You have a new support message.', 'support');
            }
        } elseif ($model instanceof LogisticsMessage) {
            $recipient = $model->thread?->user_id;
            if ($recipient && (int) $model->sender_id !== (int) $recipient) {
                $push->queue($recipient, 'logistics-message-'.$model->id, 'New logistics message',
                    'Your logistics partner sent you a message.', 'logistics_chat');
            }
        } elseif ($model instanceof DeliveryMessage) {
            $shipment = $model->shipment;
            $order = $shipment?->order ?? $shipment?->sellerOrder?->order;
            $recipient = $model->from_rider ? $order?->buyer?->id
                : ($shipment?->rider?->user_id ?? $shipment?->courier_id);
            if ($recipient && (int) $model->sender_id !== (int) $recipient) {
                $push->queue($recipient, 'delivery-message-'.$model->id, 'New delivery message',
                    'You have a new message about your parcel.', $model->from_rider ? 'order' : 'delivery',
                    $model->from_rider ? ($order?->id ?? 0) : ($shipment?->id ?? 0));
            }
        } elseif ($model instanceof Shipment) {
            $this->assignment($model, true);
        }
    }

    public function updated(Model $model): void
    {
        if ($model instanceof Shipment) $this->assignment($model);
        if ($model instanceof Rider && $model->wasChanged(['shift_start', 'shift_end', 'work_days'])) {
            app(MobilePushNotifications::class)->queue($model->user_id, 'rider-schedule-'.Str::uuid(), 'Your schedule changed',
                'Your logistics partner updated your work schedule.', 'rider_schedule');
        }
    }

    private function assignment(Shipment $shipment, bool $created = false): void
    {
        if ($created || $shipment->wasChanged(['rider_id', 'courier_id'])) {
            $recipient = $shipment->rider?->user_id ?? $shipment->courier_id;
            if ($recipient) {
                app(MobilePushNotifications::class)->queue($recipient, 'delivery-assigned-'.Str::uuid(), 'New delivery assignment',
                    'Your logistics partner assigned you a parcel. Open ZAYLO to review it.', 'delivery', $shipment->id);
            }
        }
        if (($created || $shipment->wasChanged('pickup_courier_id')) && $shipment->pickup_courier_id) {
            app(MobilePushNotifications::class)->queue($shipment->pickup_courier_id, 'pickup-assigned-'.Str::uuid(), 'New pickup assignment',
                'Your logistics partner assigned you a seller pickup.', 'rider_pickups');
        }
    }
}
