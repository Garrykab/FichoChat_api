<?php

namespace App\Actions\Auth;

use App\Services\Auth\TokenService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RefreshTokenAction
{
    public function __construct(
        private readonly TokenService $tokenService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string $refreshToken, Request $request): array
    {
        $token = $this->tokenService->findValidRefreshToken($refreshToken);

        if ($token === null) {
            $existing = $this->tokenService->findRefreshTokenByPlain($refreshToken);
            if ($existing !== null && $existing->revoked_at !== null) {
                $this->tokenService->handleRefreshTokenReuse($existing);
            }

            throw ValidationException::withMessages([
                'refresh_token' => ['The refresh token is invalid or expired.'],
            ]);
        }

        return $this->tokenService->rotateRefreshToken($token, $request);
    }
}
