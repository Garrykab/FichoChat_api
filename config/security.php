<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Force HTTPS
    |--------------------------------------------------------------------------
    |
    | Redirect HTTP → HTTPS. Derrière Railway / reverse-proxy, TrustProxies
    | doit être actif pour lire X-Forwarded-Proto.
    |
    */

    'force_https' => filter_var(
        env('FORCE_HTTPS', env('APP_ENV') === 'production'),
        FILTER_VALIDATE_BOOL,
    ),

    /*
    |--------------------------------------------------------------------------
    | Security headers
    |--------------------------------------------------------------------------
    */

    'headers' => [
        'enabled' => filter_var(env('SECURITY_HEADERS', true), FILTER_VALIDATE_BOOL),

        'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31_536_000),
        'hsts_include_subdomains' => filter_var(
            env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', true),
            FILTER_VALIDATE_BOOL,
        ),
        'hsts_preload' => filter_var(env('SECURITY_HSTS_PRELOAD', false), FILTER_VALIDATE_BOOL),

        'frame_options' => env('SECURITY_FRAME_OPTIONS', 'DENY'),
        'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'no-referrer'),
        'permissions_policy' => env(
            'SECURITY_PERMISSIONS_POLICY',
            'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()',
        ),
        'cross_origin_resource_policy' => env('SECURITY_CORP', 'cross-origin'),
        'cross_origin_opener_policy' => env('SECURITY_COOP', 'same-origin'),
        'cross_origin_embedder_policy' => env('SECURITY_COEP', ''),

        /*
         * CSP API (JSON) — stricte. Les routes Swagger / docs HTML utilisent
         * `csp_docs` ci-dessous.
         */
        'csp' => env(
            'SECURITY_CSP',
            "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'",
        ),

        'csp_docs' => env(
            'SECURITY_CSP_DOCS',
            "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'none'",
        ),
    ],

];
