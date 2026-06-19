<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds security headers to every web response.
 *
 * Applied globally via bootstrap/app.php on the web middleware group.
 * Does not affect API-style JSON responses (headers are harmless there).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent clickjacking — deny framing from any origin
        $response->headers->set('X-Frame-Options', 'DENY');

        // Stop browser from MIME-sniffing the content type
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Modern XSS auditor replacement — block reflected XSS
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Only send Referer for same-origin requests (protects tenant URLs)
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Disable browser features not needed by this app
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(self)'
        );

        // HSTS — force HTTPS for 1 year (only in production; browser ignores over HTTP)
        if (app()->isProduction()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Content Security Policy
        // 'self' + CDN for Razorpay JS + fonts + ApexCharts (inline scripts from Inertia/Vite need 'unsafe-inline')
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://checkout.razorpay.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: https:",
            "connect-src 'self' https://api.razorpay.com",
            "frame-src https://api.razorpay.com https://checkout.razorpay.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
