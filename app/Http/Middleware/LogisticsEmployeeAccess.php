<?php

namespace App\Http\Middleware;

use App\Services\LogisticsAccess;
use Closure;
use Illuminate\Http\Request;

class LogisticsEmployeeAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user?->isLogisticsEmployee()) return $next($request);
        $route = $request->route()?->getName();
        if ($route === 'logout') return $next($request);
        abort_unless(LogisticsAccess::employeeActive($user), 403, 'Your employee access or logistics company is inactive. Contact your logistics manager.');
        abort_unless(LogisticsAccess::canRoute($user, $route), 403, 'Your employee position does not permit this action.');
        if ($user->must_change_password && $user->hasVerifiedEmail()
            && ! in_array($route, ['logistics.account', 'logistics.account.password', 'verification.notice', 'verification.verify', 'verification.send'], true)) {
            return redirect()->route('logistics.account')->with('status', 'Change your temporary password before using your employee workspace.');
        }
        if ($route === 'logistics.parcels.advance') {
            abort_unless(LogisticsAccess::canAdvance($user, (string) $request->input('status')), 403, 'Your position cannot perform this parcel transition.');
        }
        return $next($request);
    }
}
