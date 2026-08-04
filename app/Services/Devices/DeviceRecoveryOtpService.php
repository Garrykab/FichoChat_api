<?php

namespace App\Services\Devices;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DeviceRecoveryOtpService
{
    private const TTL_SECONDS = 600;

    private const MAX_ATTEMPTS = 5;

    public function issue(User $user): string
    {
        $code = (string) random_int(100000, 999999);

        Cache::put($this->key($user->id), [
            'hash' => Hash::make($code),
            'attempts' => 0,
        ], self::TTL_SECONDS);

        return $code;
    }

    public function assertValid(User $user, string $code): void
    {
        $payload = Cache::get($this->key($user->id));

        if (! is_array($payload) || ! isset($payload['hash'])) {
            throw ValidationException::withMessages([
                'otp' => ['Le code a expiré ou est invalide. Demandez-en un nouveau.'],
            ]);
        }

        $attempts = (int) ($payload['attempts'] ?? 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::forget($this->key($user->id));
            throw ValidationException::withMessages([
                'otp' => ['Trop de tentatives. Demandez un nouveau code.'],
            ]);
        }

        if (! Hash::check($code, $payload['hash'])) {
            $payload['attempts'] = $attempts + 1;
            Cache::put($this->key($user->id), $payload, self::TTL_SECONDS);

            throw ValidationException::withMessages([
                'otp' => ['Code incorrect.'],
            ]);
        }
    }

    public function clear(User $user): void
    {
        Cache::forget($this->key($user->id));
    }

    private function key(string $userId): string
    {
        return 'device_recovery_otp:'.$userId;
    }
}
