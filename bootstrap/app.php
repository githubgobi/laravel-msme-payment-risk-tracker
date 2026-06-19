<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // Webhook endpoint must receive the raw body for HMAC verification; CSRF token not present
        $middleware->validateCsrfTokens(except: [
            'webhooks/razorpay',
        ]);

        $middleware->alias([
            'tenant.active' => \App\Http\Middleware\EnsureActiveTenant::class,
            'onboarding'    => \App\Http\Middleware\EnsureOnboardingComplete::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            function (Request $request, Throwable $e): bool {
                // Always redirect to login for unauthenticated requests (web Inertia SPA behaviour)
                if ($e instanceof AuthenticationException) {
                    return false;
                }
                // Return JSON for API routes or any request that explicitly accepts JSON (e.g. Axios)
                return $request->is('api/*') || $request->expectsJson();
            }
        );
    })->create();
