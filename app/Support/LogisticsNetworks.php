<?php

namespace App\Support;

use App\Models\LogisticsProvider;
use Illuminate\Support\Str;

final class LogisticsNetworks
{
    public const ZAYLO_EXPRESS = 'zaylo_express';
    public const JNT_EXPRESS = 'jnt_express';
    public const FLASH_EXPRESS = 'flash_express';

    public const NETWORKS = [
        self::ZAYLO_EXPRESS => [
            'key' => self::ZAYLO_EXPRESS,
            'name' => 'Zaylo Express Delivery',
            'short_name' => 'Zaylo Express',
            'badge' => 'Official Platform Network',
            'badge_class' => 'official',
            'icon' => 'fa-truck-fast',
            'logo' => 'images/ZAYLO_ICON_DARK.png',
            'alt_logo' => 'images/ZAYLO_LOGO_DARK.png',
            'color' => '#1a1714',
            'description' => "ZAYLO's official in-house logistics fleet and sorting hub network across the Philippines.",
            'hub_prefix' => 'Zaylo Express Delivery',
            'waybill_layout' => 'zaylo_express',
            'tagline' => 'Apply as a regional sorting hub or branch of ZAYLO Express.',
            'main_hub' => [
                'street_address' => 'Zaylo Central Hub, Km 18 West Service Road',
                'region_code' => '130000000',
                'region_name' => 'National Capital Region (NCR)',
                'province_code' => null,
                'province_name' => null,
                'city_code' => '137404000',
                'city_name' => 'Quezon City',
                'barangay_code' => '137404001',
                'barangay_name' => 'Alicia',
                'postal_code' => '1105',
            ],
        ],
        self::JNT_EXPRESS => [
            'key' => self::JNT_EXPRESS,
            'name' => 'J&T Express',
            'short_name' => 'J&T Express',
            'badge' => 'Authorized Partner Network',
            'badge_class' => 'jnt',
            'icon' => 'fa-truck-fast',
            'logo' => 'images/couriers/jnt-express.png',
            'color' => '#e11d48',
            'description' => "Operate an authorized regional branch or sorting hub of the J&T Express delivery network.",
            'hub_prefix' => 'J&T Express',
            'waybill_layout' => 'jnt_express',
            'tagline' => 'Apply as a regional franchise branch or sorting hub of J&T Express.',
            'main_hub' => [
                'street_address' => 'Purok 1',
                'region_code' => '040000000',
                'region_name' => 'Region IV-A (CALABARZON)',
                'province_code' => '043400000',
                'province_name' => 'Laguna',
                'city_code' => '043415000',
                'city_name' => 'Magdalena',
                'barangay_code' => '043415004',
                'barangay_name' => 'Baanan',
                'postal_code' => '4007',
            ],
        ],
        self::FLASH_EXPRESS => [
            'key' => self::FLASH_EXPRESS,
            'name' => 'Flash Express',
            'short_name' => 'Flash Express',
            'badge' => 'Authorized Partner Network',
            'badge_class' => 'flash',
            'icon' => 'fa-bolt-lightning',
            'logo' => 'images/couriers/flash-express.png',
            'color' => '#f59e0b',
            'description' => "Operate an authorized regional branch or sorting hub of the Flash Express delivery network.",
            'hub_prefix' => 'Flash Express',
            'waybill_layout' => 'flash_express',
            'tagline' => 'Apply as a regional franchise branch or sorting hub of Flash Express.',
            'main_hub' => [
                'street_address' => 'Rizal Street, Highway',
                'region_code' => '040000000',
                'region_name' => 'Region IV-A (CALABARZON)',
                'province_code' => '043400000',
                'province_name' => 'Laguna',
                'city_code' => '043422000',
                'city_name' => 'Pila',
                'barangay_code' => '043422005',
                'barangay_name' => 'Bulilan Sur (Pob.)',
                'postal_code' => '4010',
            ],
        ],
    ];

    public static function all(): array
    {
        return self::NETWORKS;
    }

    public static function find(?string $key): ?array
    {
        if (blank($key)) return null;
        return self::NETWORKS[$key] ?? null;
    }

    public static function isValid(?string $key): bool
    {
        return ! blank($key) && isset(self::NETWORKS[$key]);
    }

    public static function keys(): array
    {
        return array_keys(self::NETWORKS);
    }

    public static function formatBranchCompanyName(string $networkKey, string $branchName): string
    {
        $network = self::find($networkKey);
        $prefix = $network['hub_prefix'] ?? 'Logistics';
        $cleanedBranch = trim(preg_replace('/^'.preg_quote($prefix, '/').'\s*[-–—:]\s*/i', '', $branchName));
        return "{$prefix} - {$cleanedBranch}";
    }

    public static function parentProviderFor(string $networkKey): ?LogisticsProvider
    {
        if (! class_exists(LogisticsProvider::class)) return null;

        // 1. Direct match on network_brand with no parent_id (primary provider)
        $parent = LogisticsProvider::where('network_brand', $networkKey)
            ->whereNull('parent_id')
            ->first();
        if ($parent) return $parent;

        // 2. Fallback to existing seeders/records by name or slug
        return match ($networkKey) {
            self::ZAYLO_EXPRESS => LogisticsProvider::whereNull('parent_id')
                ->where(fn ($q) => $q->where('slug', 'like', '%zaylo%')->orWhere('slug', 'like', '%express-delivery%')->orWhere('name', 'like', '%Express Delivery%')->orWhere('name', 'like', '%ZAYLO%'))
                ->first(),
            self::JNT_EXPRESS => LogisticsProvider::whereNull('parent_id')
                ->where(fn ($q) => $q->where('slug', 'like', '%jt%')->orWhere('slug', 'like', '%jnt%')->orWhere('name', 'like', '%J&T%'))
                ->first(),
            self::FLASH_EXPRESS => LogisticsProvider::whereNull('parent_id')
                ->where(fn ($q) => $q->where('slug', 'like', '%flash%')->orWhere('name', 'like', '%Flash%'))
                ->first(),
            default => null,
        };
    }

    public static function mainHubAddressFor(string $networkKey): ?array
    {
        $parent = self::parentProviderFor($networkKey);
        if ($parent && filled($parent->street_address) && filled($parent->city_code)) {
            return [
                'street_address' => $parent->street_address,
                'region_code' => $parent->region_code,
                'region_name' => $parent->region_name,
                'province_code' => $parent->province_code,
                'province_name' => $parent->province_name,
                'city_code' => $parent->city_code,
                'city_name' => $parent->city_name,
                'barangay_code' => $parent->barangay_code,
                'barangay_name' => $parent->barangay_name,
                'postal_code' => $parent->postal_code,
            ];
        }

        $meta = self::find($networkKey);
        return $meta['main_hub'] ?? null;
    }

    public static function isSameAddressAsMain(string $networkKey, array $hubAddress): bool
    {
        $main = self::mainHubAddressFor($networkKey);
        if (! $main || blank($main['street_address'] ?? null) || blank($main['city_code'] ?? null)) {
            return false;
        }

        $hubStreet = $hubAddress['street_address'] ?? null;
        $hubCity = $hubAddress['city_code'] ?? null;
        $hubBrgy = $hubAddress['barangay_code'] ?? null;

        if (blank($hubStreet) || blank($hubCity)) {
            return false;
        }

        // Must match city
        if ((string) $hubCity !== (string) $main['city_code']) {
            return false;
        }

        // If main has barangay, must match barangay
        if (filled($main['barangay_code'] ?? null) && filled($hubBrgy) && (string) $hubBrgy !== (string) $main['barangay_code']) {
            return false;
        }

        $normMain = self::normalizeAddressText((string) $main['street_address']);
        $normHub = self::normalizeAddressText((string) $hubStreet);

        if ($normMain === '' || $normHub === '') {
            return false;
        }

        return $normMain === $normHub || str_contains($normMain, $normHub) || str_contains($normHub, $normMain);
    }

    public static function normalizeAddressText(string $text): string
    {
        $str = strtolower(trim($text));
        $replacements = [
            '/\bstreet\b/' => 'st',
            '/\bavenue\b/' => 'ave',
            '/\broad\b/' => 'rd',
            '/\bdrive\b/' => 'dr',
            '/\bbuilding\b/' => 'bldg',
            '/\bbarangay\b/' => 'brgy',
            '/\bpoblacion\b/' => 'pob',
            '/\bhighway\b/' => 'hwy',
            '/\bblock\b/' => 'blk',
            '/\blot\b/' => 'lt',
            '/\bfloor\b/' => 'flr',
            '/\bunit\b/' => 'unit',
        ];
        $str = preg_replace(array_keys($replacements), array_values($replacements), $str);
        return preg_replace('/[^a-z0-9]/', '', $str);
    }
}

