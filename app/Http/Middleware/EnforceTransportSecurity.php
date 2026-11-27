<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceTransportSecurity
{
    public function handle(Request $request, Closure $next): Response
    {
        $forceHttps = (bool) config('security.force_https', false);

        if ($forceHttps && ! $request->secure()) {
            if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
                return redirect()->secure($request->getRequestUri(), 301);
            }

            return response()->json([
                'message' => 'HTTPS is required for this endpoint.',
            ], 426);
        }

        $response = $next($request);

        if ($forceHttps || $request->secure()) {
            $maxAge = (int) config('security.hsts_max_age', 31536000);
            $hsts = "max-age={$maxAge}";
            if (config('security.hsts_include_subdomains', true)) {
                $hsts .= '; includeSubDomains';
            }

            $response->headers->set('Strict-Transport-Security', $hsts);
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
