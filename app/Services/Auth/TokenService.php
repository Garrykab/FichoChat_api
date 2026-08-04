<?php

namespace App\Services\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class TokenService
{
    public function issueTokenPair(User $user, Request $request, ?string $deviceId = null): array
    {
        $accessToken = JWTAuth::fromUser($user);
        $refreshToken = $this->createRefreshToken($user, $request, $deviceId);

        return [
            'access_token' => $accessToken,
            'token_type' => 'bearer',
            'expires_in' => (int) config('jwt.ttl') * 60,
            'refresh_token' => $refreshToken,
        ];
    }

    public function createRefreshToken(User $user, Request $request, ?string $deviceId = null): string
    {
        $plainToken = Str::random(64);

        RefreshToken::query()->create([
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays((int) config('auth.refresh_token_days', 30)),
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return $plainToken;
    }

    public function findValidRefreshToken(string $plainToken): ?RefreshToken
    {
        /** @var RefreshToken|null $token */
        $token = RefreshToken::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if ($token === null || ! $token->isValid()) {
            return null;
        }

        return $token;
    }

    /**
     * Lookup by plain token regardless of validity (reuse detection).
     */
    public function findRefreshTokenByPlain(string $plainToken): ?RefreshToken
    {
        /** @var RefreshToken|null $token */
        $token = RefreshToken::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        return $token;
    }

    /**
     * Refresh déjà révoqué / expiré réutilisé → révoque toute la famille (user ou device).
     */
    public function handleRefreshTokenReuse(RefreshToken $token): void
    {
        if ($token->device_id) {
            $this->revokeAllForDevice($token->device_id);

            return;
        }

        $this->revokeAllForUser($token->user);
    }

    public function rotateRefreshToken(RefreshToken $current, Request $request): array
    {
        $current->forceFill([
            'revoked_at' => now(),
            'last_used_at' => now(),
        ])->save();

        $user = $current->user;

        return $this->issueTokenPair($user, $request, $current->device_id);
    }

    public function revokeRefreshToken(string $plainToken): bool
    {
        $token = $this->findRefreshTokenByPlain($plainToken);

        if ($token === null || $token->revoked_at !== null) {
            return false;
        }

        $token->forceFill(['revoked_at' => now()])->save();

        return true;
    }

    public function revokeAllForUser(User $user): void
    {
        $user->refreshTokens()
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function revokeAllForDevice(string $deviceId): void
    {
        RefreshToken::query()
            ->where('device_id', $deviceId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function verifyPassword(User $user, string $password): bool
    {
        return Hash::check($password, $user->password);
    }
}
