<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirige les requêtes HTTP vers HTTPS (prod / FORCE_HTTPS=true).
 */
class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.force_https')) {
            return $next($request);
        }

        // Healthcheck Railway / LB : laisser passer sans redirect (souvent HTTP interne).
        if ($request->is('up')) {
            return $next($request);
        }

        if ($request->secure()) {
            return $next($request);
        }

        $httpsUrl = 'https://'.$request->getHttpHost().$request->getRequestUri();

        return redirect()->to($httpsUrl, 301);
    }
}
