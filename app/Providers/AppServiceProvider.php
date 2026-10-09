<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiting();
    }

    /**
     * Limits for the public (no login) checkout endpoints, per IP address.
     * Generous enough that a real customer never notices them.
     */
    protected function configureRateLimiting(): void
    {
        // Each order creates an order + invoice and sends an email.
        RateLimiter::for('checkout-order', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip())->response(
                fn (Request $request, array $headers) => response()->json([
                    'success' => false,
                    'message' => 'Too many order attempts from your connection. Please wait a minute and try again.',
                ], 429, $headers)
            );
        });

        // Each call goes out to the Google / Novex APIs. Separate counter per endpoint.
        RateLimiter::for('checkout-quote', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip() . '|' . $request->path())->response(
                fn (Request $request, array $headers) => response()->json([
                    'success' => false,
                    'error' => 'Too many requests. Please wait a minute and try again.',
                    'message' => 'Too many requests. Please wait a minute and try again.',
                ], 429, $headers)
            );
        });

        // The payment gateway redirects the customer's browser here; this only slows guessing.
        RateLimiter::for('payment-callback', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip())->response(
                fn (Request $request, array $headers) => response()->view('website.order.payment-result', [
                    'success' => false,
                    'heading' => 'Too Many Requests',
                    'message' => 'Too many requests from your connection. Please wait a minute and try again.',
                ], 429, $headers)
            );
        });
    }
}
