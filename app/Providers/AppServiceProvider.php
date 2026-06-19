<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // Login: 5 attempts per minute per IP — reduces brute-force credential attacks
        RateLimiter::for('login', fn (Request $request) =>
            Limit::perMinute(5)->by($request->ip())
        );

        // Registration: 3 per minute per IP — prevents automated account creation
        RateLimiter::for('register', fn (Request $request) =>
            Limit::perMinute(3)->by($request->ip())
        );

        // Calculator API: 30 per minute per user — this endpoint is called on every keystroke
        RateLimiter::for('calculator', fn (Request $request) =>
            Limit::perMinute(30)->by($request->user()?->id ?: $request->ip())
        );

        // File import: 5 per minute per tenant — each import is expensive (queue + storage)
        RateLimiter::for('import', fn (Request $request) =>
            Limit::perMinute(5)->by($request->user()?->tenant_id ?: $request->ip())
        );

        // Udyam API: 10 per minute per user — Surepass charges per verification call
        RateLimiter::for('udyam', fn (Request $request) =>
            Limit::perMinute(10)->by($request->user()?->id ?: $request->ip())
        );

        // Razorpay webhooks: 120 per minute per IP — allow burst from Razorpay's servers
        RateLimiter::for('webhooks', fn (Request $request) =>
            Limit::perMinute(120)->by($request->ip())
        );
    }
}
