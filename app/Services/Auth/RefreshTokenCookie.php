<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class RefreshTokenCookie
{
    public const CLIENT_WEB = 'web';

    public function name(): string
    {
        return (string) config('auth.refresh_cookie.name', 'fichochat_refresh_token');
    }

    public function isWebClient(Request $request): bool
    {
        return strtolower((string) $request->header('X-Client', '')) === self::CLIENT_WEB;
    }

    public function read(Request $request): ?string
    {
        $value = $request->cookie($this->name());
        if (! is_string($value) || $value === '') {
            $raw = $request->cookies->get($this->name());
            $value = is_string($raw) ? $raw : null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Resolve refresh token: body (mobile) wins if present, else cookie (web).
     */
    public function resolvePlainToken(Request $request): ?string
    {
        $fromBody = $request->input('refresh_token');
        if (is_string($fromBody) && $fromBody !== '') {
            return $fromBody;
        }

        return $this->read($request);
    }

    public function make(string $plainRefreshToken): Cookie
    {
        $minutes = (int) config('auth.refresh_cookie.minutes', 60 * 24 * 30);
        $secure = config('auth.refresh_cookie.secure');
        if ($secure === null || $secure === '') {
            $secure = app()->isProduction();
        } else {
            $secure = filter_var($secure, FILTER_VALIDATE_BOOLEAN);
        }

        return cookie(
            name: $this->name(),
            value: $plainRefreshToken,
            minutes: $minutes,
            path: (string) config('auth.refresh_cookie.path', '/api/v1/auth'),
            domain: config('auth.refresh_cookie.domain'),
            secure: (bool) $secure,
            httpOnly: true,
            raw: false,
            sameSite: (string) config('auth.refresh_cookie.same_site', 'lax'),
        );
    }

    public function forget(): Cookie
    {
        $secure = config('auth.refresh_cookie.secure');
        if ($secure === null || $secure === '') {
            $secure = app()->isProduction();
        } else {
            $secure = filter_var($secure, FILTER_VALIDATE_BOOLEAN);
        }

        return cookie(
            name: $this->name(),
            value: '',
            minutes: -1,
            path: (string) config('auth.refresh_cookie.path', '/api/v1/auth'),
            domain: config('auth.refresh_cookie.domain'),
            secure: (bool) $secure,
            httpOnly: true,
            raw: false,
            sameSite: (string) config('auth.refresh_cookie.same_site', 'lax'),
        );
    }

    /**
     * @param  array<string, mixed>  $tokens
     * @return array{tokens: array<string, mixed>, cookie: Cookie|null}
     */
    public function packageForClient(Request $request, array $tokens): array
    {
        if (! $this->isWebClient($request)) {
            return ['tokens' => $tokens, 'cookie' => null];
        }

        $refresh = $tokens['refresh_token'] ?? null;
        unset($tokens['refresh_token']);

        return [
            'tokens' => $tokens,
            'cookie' => is_string($refresh) ? $this->make($refresh) : null,
        ];
    }
}
