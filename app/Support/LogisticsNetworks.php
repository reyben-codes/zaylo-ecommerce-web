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
}
