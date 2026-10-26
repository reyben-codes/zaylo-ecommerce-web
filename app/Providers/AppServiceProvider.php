<?php

namespace App\Providers;

use App\Models\CartItem;
use App\Models\MobileToken;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\BuyerNotificationFeed;
use App\Services\WorkspaceNotificationFeed;
use App\Support\SnapshotSchemaBuilder;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {

        // Web requests answer schema checks from one snapshot query instead of a round trip each.
        // Console commands (migrations above all) keep the stock builder; see SnapshotSchemaBuilder.
        if (! $this->app->runningInConsole()) {
            $this->app->bind('db.schema', function ($app) {
                $connection = $app['db']->connection();

                return in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)
                    ? new SnapshotSchemaBuilder($connection)
                    : $connection->getSchemaBuilder();
            });
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('partials.seller-header', function ($view) {
            $view->with('sellerHeaderStoreName', auth()->user()?->sellerStoreName() ?? 'My Shop');
        });

        // Unread notifications on the Admin and Seller Center sidebars, plus the seller's account and product warnings.
        View::composer(['partials.seller-sidebar', 'layouts.workspace', 'seller.partials.alert-banner'], function ($view) {
            $user = auth()->user();
            $feed = app(WorkspaceNotificationFeed::class);
            $view->with([
                'sidebarUnreadNotifications' => $user ? $feed->unreadCount($user) : 0,
                'sidebarUrgentNotifications' => $user ? $feed->urgent($user) : collect(),
            ]);
        });

        View::composer('partials.buyer-nav-icons', function ($view) {
            $buyer = auth()->user();

            $view->with([
                'navCartQuantity' => (int) CartItem::whereHas('cart', fn ($query) => $query->where('user_id', $buyer->id))->sum('quantity'),
                'navWishlistCount' => $buyer->wishlistItems()->count(),
                'navUnreadNotificationCount' => app(BuyerNotificationFeed::class)->forUser($buyer)->where('read', false)->count(),
            ]);
        });

        // Product cards fill the heart for saved products; looked up once per request.
        View::composer('partials.shop-card', function ($view) {
            $request = request();
            if (! $request->attributes->has('savedProductIds')) {
                $viewer = auth()->user();
                $request->attributes->set('savedProductIds', $viewer?->role === 'buyer'
                    ? $viewer->wishlistItems()->pluck('product_id')->flip()->all()
                    : []);
            }
            $view->with('savedProductIds', $request->attributes->get('savedProductIds'));
        });

        // Mobile app sign-ins: a bearer token from personal_access_tokens, checked on every API request.
        Auth::viaRequest('mobile-token', function (Request $request) {
            if (! Schema::hasTable('personal_access_tokens')) {
                return null;
            }
            $token = MobileToken::findForBearer($request->bearerToken());
            $user = $token?->tokenable;
            if (! $user instanceof User) {
                return null;
            }
            if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinute())) {
                $token->forceFill(['last_used_at' => now()])->saveQuietly();
            }

            return $user->withAccessToken($token);
        });

        // Activity logs for sign-in events that happen without a signed-in account (the rest: config/activity.php).
        Event::listen(function (Failed $event) {
            if ($event->user instanceof User) {
                app(ActivityLogger::class)->record('auth.failed', 'auth', 'Failed sign-in attempt (wrong password)', $event->user);
            }
        });
        Event::listen(function (PasswordReset $event) {
            if ($event->user instanceof User) {
                app(ActivityLogger::class)->record('auth.password_reset', 'auth', 'Reset password from the emailed link', $event->user);
            }
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });
    }
}
