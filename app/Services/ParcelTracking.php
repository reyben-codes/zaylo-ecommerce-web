<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Parcel tracking on a map. Buyers pin the exact spot of a saved address; checkout copies the pin to the order, and
 * the rider's app shows it. While a parcel is out for delivery, the rider app reports the rider's position every few
 * seconds while it is open, and the buyer sees it moving towards the pin. The position is shown only while the
 * parcel is out for delivery, and the time of the last report says how fresh it is.
 */
class ParcelTracking
{
    /** The Philippines with a margin, so a pin far outside it is a mistake. */
    public const BOUNDS = ['lat' => [4.0, 21.5], 'lng' => [116.0, 127.5]];

    /** How often the rider app reports while open, in seconds; the buyer's map refreshes as often. */
    public const REPORT_SECONDS = 15;

    public static function pinsAddresses(): bool
    {
        return Schema::hasColumn('addresses', 'latitude');
    }

    public static function pinsOrders(): bool
    {
        return Schema::hasColumn('orders', 'delivery_latitude');
    }

    public static function tracksRiders(): bool
    {
        return Schema::hasColumn('shipments', 'rider_latitude');
    }

    /** Validation for an optional pin: both coordinates or neither, inside the Philippines. */
    public static function pinRules(string $latitude = 'latitude', string $longitude = 'longitude'): array
    {
        return [
            $latitude => ['nullable', "required_with:$longitude", 'numeric', 'between:'.implode(',', self::BOUNDS['lat'])],
            $longitude => ['nullable', "required_with:$latitude", 'numeric', 'between:'.implode(',', self::BOUNDS['lng'])],
        ];
    }

    /** @return array{latitude: float, longitude: float}|null */
    public static function point(mixed $latitude, mixed $longitude): ?array
    {
        return is_numeric($latitude) && is_numeric($longitude)
            ? ['latitude' => round((float) $latitude, 7), 'longitude' => round((float) $longitude, 7)]
            : null;
    }

    public static function addressPin(?Address $address): ?array
    {
        return $address && self::pinsAddresses() ? self::point($address->latitude, $address->longitude) : null;
    }

    /** The order's delivery pin, as the buyer placed it at checkout. */
    public static function destination(?Order $order): ?array
    {
        return $order && self::pinsOrders() ? self::point($order->delivery_latitude, $order->delivery_longitude) : null;
    }

    /** What checkout copies from the address onto the order. */
    public static function orderPin(Address $address): array
    {
        if (! self::pinsOrders()) {
            return [];
        }
        $pin = self::addressPin($address);

        return ['delivery_latitude' => $pin['latitude'] ?? null, 'delivery_longitude' => $pin['longitude'] ?? null];
    }

    /** The rider's last reported position, only while the parcel is out for delivery. */
    public static function riderPosition(?Shipment $shipment): ?array
    {
        if (! $shipment || ! self::tracksRiders() || ShipmentWorkflow::stageOf($shipment) !== 'out_for_delivery') {
            return null;
        }
        $point = self::point($shipment->rider_latitude, $shipment->rider_longitude);

        return $point ? $point + ['updated_at' => $shipment->rider_located_at?->toIso8601String()] : null;
    }

    /** What the buyer's tracking map shows. */
    public function forBuyer(Order $order): array
    {
        $shipment = $order->deliveryShipment();
        $stage = $shipment ? ShipmentWorkflow::stageOf($shipment) : null;
        $rider = self::riderPosition($shipment);

        return [
            'stage' => $stage,
            'stage_label' => $stage ? ShipmentWorkflow::label($stage) : 'Preparing your order',
            'message' => match (true) {
                $rider !== null => 'Your rider is on the way. The map updates every '.self::REPORT_SECONDS.' seconds.',
                $stage === 'out_for_delivery' => 'Your rider is on the way. Their location appears once their app shares it.',
                in_array($stage, ['delivered', 'completed'], true) => 'Your parcel was delivered.',
                $stage === null => 'Your parcel appears here once the seller ships it.',
                default => 'Your parcel is with '.($stage === 'ready' ? 'the seller' : 'the logistics partner').'. You can follow the rider once it is out for delivery.',
            },
            'destination' => self::destination($order),
            'rider' => $rider,
            'refresh_seconds' => self::REPORT_SECONDS,
        ];
    }

    /**
     * The rider app reports where the rider is: every parcel the rider has out for delivery takes the position.
     * Returns how many parcels it updated.
     */
    public function report(User $rider, float $latitude, float $longitude): int
    {
        if (! self::tracksRiders()) {
            return 0;
        }
        if (! self::inBounds($latitude, $longitude)) {
            throw ValidationException::withMessages(['latitude' => 'That location is outside the Philippines.']);
        }
        $ids = ShipmentWorkflow::whereStage(app(RiderDeliveries::class)->forRider($rider), ['out_for_delivery'])->pluck('id');

        return $ids->isEmpty() ? 0 : Shipment::whereKey($ids)->update([
            'rider_latitude' => round($latitude, 7), 'rider_longitude' => round($longitude, 7), 'rider_located_at' => now(),
        ]);
    }

    public static function inBounds(float $latitude, float $longitude): bool
    {
        return $latitude >= self::BOUNDS['lat'][0] && $latitude <= self::BOUNDS['lat'][1]
            && $longitude >= self::BOUNDS['lng'][0] && $longitude <= self::BOUNDS['lng'][1];
    }
}
