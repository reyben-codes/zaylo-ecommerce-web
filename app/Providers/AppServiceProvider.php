<?php

namespace App\Providers;

use App\Models\CartItem;
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
        View::composer('partials.buyer-nav-icons', function ($view) {
            $buyer = auth()->user();

            $view->with([
                'navCartQuantity' => (int) CartItem::whereHas('cart', fn ($query) => $query->where('user_id', $buyer->id))->sum('quantity'),
                'navWishlistCount' => $buyer->wishlistItems()->count(),
            ]);
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });
    }
}
