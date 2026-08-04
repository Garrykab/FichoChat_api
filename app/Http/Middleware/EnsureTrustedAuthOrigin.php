<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protège les endpoints qui lisent le cookie refresh (CSRF soft via Origin/Referer).
 */
class EnsureTrustedAuthOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        $cookieName = (string) config('auth.refresh_cookie.name', 'fichochat_refresh_token');
        $usesCookie = $request->hasCookie($cookieName)
            && ! $request->filled('refresh_token');

        if (! $usesCookie && strtolower((string) $request->header('X-Client', '')) !== 'web') {
            return $next($request);
        }

        $allowed = $this->allowedOrigins();
        if ($allowed === []) {
            return $next($request);
        }

        $origin = $request->headers->get('Origin');
        if ($origin !== null && $origin !== '') {
            if (! in_array($origin, $allowed, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Origin not allowed.',
                ], 403);
            }

            return $next($request);
        }

        $referer = $request->headers->get('Referer');
        if ($referer !== null && $referer !== '') {
            foreach ($allowed as $allowedOrigin) {
                if (str_starts_with($referer, rtrim($allowedOrigin, '/').'/')
                    || rtrim($referer, '/') === rtrim($allowedOrigin, '/')) {
                    return $next($request);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Referer not allowed.',
            ], 403);
        }

        // Pas d’Origin/Referer (ex. curl mobile) : OK si pas cookie-only
        if ($usesCookie) {
            return response()->json([
                'success' => false,
                'message' => 'Missing Origin for cookie-based auth.',
            ], 403);
        }

        return $next($request);
    }

    /**
     * @return list<string>
     */
    private function allowedOrigins(): array
    {
        $fromCors = config('cors.allowed_origins', []);
        if (is_array($fromCors) && $fromCors !== []) {
            return array_values(array_filter(array_map('strval', $fromCors)));
        }

        $frontend = (string) config('app.frontend_url', '');

        return $frontend !== '' ? [$frontend] : [];
    }
}
