<?php

namespace App\Providers;

use App\Models\CartItem;
use App\Services\BuyerNotificationFeed;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('partials.seller-header', function ($view) {
            $view->with('sellerHeaderStoreName', auth()->user()?->sellerStoreName() ?? 'My Shop');
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

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });
    }
}
