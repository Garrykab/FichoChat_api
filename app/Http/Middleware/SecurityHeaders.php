<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité HTTP pour l’API (et docs Swagger assouplies).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! config('security.headers.enabled', true)) {
            return $response;
        }

        $headers = config('security.headers', []);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', (string) ($headers['frame_options'] ?? 'DENY'));
        $response->headers->set('Referrer-Policy', (string) ($headers['referrer_policy'] ?? 'no-referrer'));
        $response->headers->set('X-XSS-Protection', '0');
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        $permissions = (string) ($headers['permissions_policy'] ?? '');
        if ($permissions !== '') {
            $response->headers->set('Permissions-Policy', $permissions);
        }

        $corp = (string) ($headers['cross_origin_resource_policy'] ?? '');
        if ($corp !== '') {
            $response->headers->set('Cross-Origin-Resource-Policy', $corp);
        }

        $coop = (string) ($headers['cross_origin_opener_policy'] ?? '');
        if ($coop !== '') {
            $response->headers->set('Cross-Origin-Opener-Policy', $coop);
        }

        $coep = (string) ($headers['cross_origin_embedder_policy'] ?? '');
        if ($coep !== '') {
            $response->headers->set('Cross-Origin-Embedder-Policy', $coep);
        }

        $csp = $this->isDocsRequest($request)
            ? (string) ($headers['csp_docs'] ?? '')
            : (string) ($headers['csp'] ?? '');

        if ($csp !== '') {
            $response->headers->set('Content-Security-Policy', $csp);
        }

        if ($request->secure()) {
            $maxAge = (int) ($headers['hsts_max_age'] ?? 31_536_000);
            $hsts = "max-age={$maxAge}";

            if (! empty($headers['hsts_include_subdomains'])) {
                $hsts .= '; includeSubDomains';
            }

            if (! empty($headers['hsts_preload'])) {
                $hsts .= '; preload';
            }

            $response->headers->set('Strict-Transport-Security', $hsts);
        }

        return $response;
    }

    private function isDocsRequest(Request $request): bool
    {
        return $request->is('api/documentation')
            || $request->is('api/documentation/*')
            || $request->is('docs')
            || $request->is('docs/*');
    }
}
