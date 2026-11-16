<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

class LogisticsAccess
{
    public static function canRoute(?User $user, ?string $route): bool
    {
        if (! $user?->hasRole('logistics')) return false;
        if (! $user->isLogisticsEmployee()) return true;
        if (! self::employeeActive($user)) return false;
        $position = config('logistics_employees.positions.'.$user->logistics_position);
        return $position && in_array($route, array_merge(config('logistics_employees.common'), $position['routes']), true);
    }

    public static function employeeActive(User $user): bool
    {
        if (! $user->isActive() || ! $user->logistics_employee_provider_id || ! config('logistics_employees.positions.'.$user->logistics_position)) return false;
        $company = $user->logisticsProvider;
        return $company && $company->status === 'approved' && $company->owner?->isActive();
    }

    public static function canAdvance(User $user, string $status): bool
    {
        return ! $user->isLogisticsEmployee() || (self::canRoute($user, 'logistics.parcels.advance')
            && in_array($status, config('logistics_employees.positions.'.$user->logistics_position.'.advance', []), true));
    }

    public static function canUrl(User $user, string $url): bool
    {
        if (! $user->isLogisticsEmployee()) return true;
        try {
            return self::canRoute($user, app('router')->getRoutes()->match(Request::create($url))->getName());
        } catch (\Throwable) {
            return false;
        }
    }
}
