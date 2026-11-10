<?php

namespace App\Services;

use App\Models\Address;
use App\Models\LogisticsProvider;
use Illuminate\Support\Collection;

/**
 * The shipping fee of a parcel, by how far the delivery address is from the hub of the logistics partner the buyer
 * picked. Distance is measured in zones from the PSGC codes both addresses carry (same city or municipality, same
 * province, same region, same island group, or across island groups), as couriers like J&T and LBC price it.
 * Without a partner, or when either side has no PSGC codes, the old flat fee applies.
 */
class ShippingRates
{
    public const STANDARD_FEE = 120.00;

    public const ZONES = [
        'same_city' => ['label' => 'Same city or municipality', 'fee' => 60.00],
        'same_province' => ['label' => 'Same province', 'fee' => 85.00],
        'same_region' => ['label' => 'Same region', 'fee' => 110.00],
        'same_island_group' => ['label' => 'Same island group', 'fee' => 150.00],
        'other_island_group' => ['label' => 'Another island group', 'fee' => 200.00],
    ];

    /** PSGC region codes (the first two digits) by island group. */
    private const ISLAND_GROUPS = [
        'luzon' => ['01', '02', '03', '04', '05', '13', '14', '17'],
        'visayas' => ['06', '07', '08', '18'],
        'mindanao' => ['09', '10', '11', '12', '15', '16', '19'],
    ];

    /** The zone between a partner's hub and a delivery address, or null when it cannot be told. */
    public static function zone(?LogisticsProvider $hub, ?Address $to): ?string
    {
        $from = self::codes($hub?->region_code, $hub?->province_code, $hub?->city_code);
        $dest = self::codes($to?->region_code, $to?->province_code, $to?->city_municipality_code);
        if (! $from || ! $dest) {
            return null;
        }

        return match (true) {
            $from['city'] !== null && $from['city'] === $dest['city'] => 'same_city',
            // Metro Manila and a few other areas have no province: the region is the province.
            $from['region'] === $dest['region'] && $from['province'] === $dest['province'] => 'same_province',
            $from['region'] === $dest['region'] => 'same_region',
            self::islandGroup($from['region']) !== null && self::islandGroup($from['region']) === self::islandGroup($dest['region']) => 'same_island_group',
            default => 'other_island_group',
        };
    }

    public static function fee(?string $zone): float
    {
        // The COD commission policy has one nationwide shipping charge. Zones
        // remain useful for routing and logistics-partner selection.
        return self::STANDARD_FEE;
    }

    public static function label(?string $zone): string
    {
        return self::ZONES[$zone]['label'] ?? 'Standard rate';
    }

    /**
     * The fee each partner charges per seller to each address, for checkout to show and recalculate as the buyer
     * changes either one.
     *
     * @param  Collection<int, LogisticsProvider>  $partners
     * @param  Collection<int, Address>  $addresses
     * @return Collection<int, array{id:int, name:string, hub:string, rates:array<int, array{zone:?string, label:string, fee:float}>}>
     */
    public static function table(Collection $partners, Collection $addresses): Collection
    {
        return $partners->map(fn (LogisticsProvider $partner) => [
            'id' => $partner->id,
            'name' => $partner->name,
            'hub' => collect([$partner->city_name, $partner->province_name ?: $partner->region_name])->filter()->implode(', '),
            'rates' => $addresses->mapWithKeys(function (Address $address) use ($partner) {
                $zone = self::zone($partner, $address);

                return [$address->id => ['zone' => $zone, 'label' => self::label($zone), 'fee' => self::fee($zone)]];
            })->all(),
        ])->values();
    }

    /**
     * The partner with the lowest fee to an address (ties go to the first listed), for orders that name none, such
     * as orders from older app versions.
     *
     * @param  Collection<int, LogisticsProvider>  $partners
     */
    public static function nearest(Collection $partners, Address $to): ?LogisticsProvider
    {
        return $partners->sortBy(fn (LogisticsProvider $partner) => self::fee(self::zone($partner, $to)))->first();
    }

    private static function islandGroup(string $region): ?string
    {
        foreach (self::ISLAND_GROUPS as $group => $regions) {
            if (in_array($region, $regions, true)) {
                return $group;
            }
        }

        return null;
    }

    /** @return array{region:string, province:?string, city:?string}|null */
    private static function codes(?string $region, ?string $province, ?string $city): ?array
    {
        if (! preg_match('/^[0-9]{9,10}$/D', (string) $region)) {
            return null;
        }

        return [
            'region' => substr($region, 0, 2),
            'province' => filled($province) ? (string) $province : null,
            'city' => filled($city) ? (string) $city : null,
        ];
    }
}
