<?php

namespace App\Services;

use App\Models\Rider;
use App\Models\Shipment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Orders riders by how close they are to a parcel, so logistics gives each area to riders who are already there and
 * parcels do not get mixed up between areas:
 *   1. riders whose app reported a position in the last FRESH_HOURS, by distance to the parcel's delivery pin;
 *   2. riders who live in the parcel's city, then in its province (their saved home address);
 *   3. everyone else ("location unknown").
 */
class RiderProximity
{
    public const FRESH_HOURS = 2;

    public static function tracks(): bool
    {
        return Schema::hasColumn('riders', 'last_latitude');
    }

    /** Only riders with a saved address in the delivery city can be offered for that area. */
    public function serving(Collection $riders, ?string $area): Collection
    {
        [$city, $province] = self::areaParts($area);
        if ($city === '') {
            return collect();
        }

        return $riders->filter(function (Rider $rider) use ($city, $province) {
            $home = $rider->user?->addresses?->sortByDesc('is_default')->first();
            return $rider->is_active && $rider->status === 'approved'
                && $rider->user?->status === 'active'
                && self::clean($home?->city) === $city
                && ($province === '' || self::clean($home?->province ?: $home?->region_name) === $province);
        })->values();
    }

    /** The app reported where the rider is (on shift or out for delivery). */
    public static function remember(int $userId, float $latitude, float $longitude): void
    {
        if (self::tracks()) {
            Rider::where('user_id', $userId)->update(['last_latitude' => round($latitude, 7), 'last_longitude' => round($longitude, 7), 'last_located_at' => now()]);
        }
    }

    /**
     * @param  Collection<int, Rider>  $riders  with user.addresses loaded or loadable
     * @param  string|null  $area  "City, Province" as the hub sorted the parcel
     * @param  array{latitude: float, longitude: float}|null  $pin  the buyer's delivery pin
     * @return Collection<int, array{rider: Rider, label: string, rank: int, km: ?float}>
     */
    public function rank(Collection $riders, ?string $area, ?array $pin = null): Collection
    {
        [$city, $province] = self::areaParts($area);

        return $riders->map(function (Rider $rider) use ($city, $province, $pin) {
            $fresh = self::tracks() && $rider->last_located_at && $rider->last_located_at->gt(now()->subHours(self::FRESH_HOURS))
                && $rider->last_latitude !== null;
            if ($fresh && $pin) {
                $km = self::km((float) $rider->last_latitude, (float) $rider->last_longitude, $pin['latitude'], $pin['longitude']);

                return ['rider' => $rider, 'rank' => 0, 'km' => $km, 'label' => number_format($km, 1).' km away'];
            }
            // The rider's saved home address (addresses.city / province).
            $home = $rider->user?->addresses?->sortByDesc('is_default')->first();
            $homeCity = self::clean($home?->city);
            $homeProvince = self::clean($home?->province ?: $home?->region_name);
            if ($city !== '' && $homeCity !== '' && ($homeCity === $city || str_contains($homeCity, $city) || str_contains($city, $homeCity))) {
                return ['rider' => $rider, 'rank' => 1, 'km' => null, 'label' => 'lives in '.$home->city];
            }
            if ($province !== '' && $homeProvince !== '' && ($homeProvince === $province || str_contains($homeProvince, $province) || str_contains($province, $homeProvince))) {
                return ['rider' => $rider, 'rank' => 2, 'km' => null, 'label' => 'lives in '.($home->province ?: $home->region_name)];
            }

            return ['rider' => $rider, 'rank' => $fresh ? 2 : 3, 'km' => null, 'label' => $home?->city ? 'lives in '.$home->city : 'location unknown'];
        })->sort(fn (array $a, array $b) => [$a['rank'], $a['km'] ?? 0, $a['rider']->user?->name ?? ''] <=> [$b['rank'], $b['km'] ?? 0, $b['rider']->user?->name ?? ''])
            ->values();
    }

    /** The delivery pin of a parcel's order, when the buyer pinned one. */
    public static function pinOf(?Shipment $shipment): ?array
    {
        $order = $shipment?->order ?? $shipment?->sellerOrder?->order;

        return ParcelTracking::destination($order);
    }

    /** Where a parcel is going, as "City, Province" (its sorted area, or the city on the order's address). */
    public static function areaOf(?Shipment $shipment): ?string
    {
        if ($shipment?->getAttribute('sort_area')) {
            return $shipment->sort_area;
        }
        $address = $shipment?->order?->shipping_address ?? $shipment?->sellerOrder?->order?->shipping_address;
        if (is_array($address)) {
            return collect([$address['city'] ?? null, $address['province'] ?? null])->filter()->implode(', ') ?: null;
        }

        return is_string($address) ? collect(explode(',', $address))->map(fn ($part) => trim($part))->filter()->slice(-3, 2)->implode(', ') : null;
    }

    public static function km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $rad = M_PI / 180;
        $a = sin(($lat2 - $lat1) * $rad / 2) ** 2 + cos($lat1 * $rad) * cos($lat2 * $rad) * sin(($lng2 - $lng1) * $rad / 2) ** 2;

        return round(6371 * 2 * atan2(sqrt($a), sqrt(1 - $a)), 1);
    }

    /** @return array{0: string, 1: string} city and province of "City, Province", normalised */
    private static function areaParts(?string $area): array
    {
        $parts = array_map('trim', explode(',', (string) $area));

        return [self::clean($parts[0] ?? ''), self::clean($parts[1] ?? '')];
    }

    private static function clean(?string $name): string
    {
        $name = Str::lower(Str::ascii((string) $name));

        return trim(preg_replace('/\b(city of|city|municipality of)\b/', '', $name));
    }
}
