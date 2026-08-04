<?php

namespace App\Actions\Devices;

use App\Enums\DevicePlatform;
use App\Enums\DeviceStatus;
use App\Models\DeviceRecoveryBackup;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Auth\TokenService;
use App\Services\Devices\DeviceRecoveryOtpService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RestoreWithRecoveryPhraseAction
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly DeviceRecoveryOtpService $otpService,
        private readonly PromoteRecoveredDeviceAction $promoteRecoveredDeviceAction,
    ) {}

    /**
     * V2 — restauration via phrase : réactive l’appareil au fingerprint du backup.
     *
     * @param  array{
     *     password: string,
     *     otp: string,
     *     public_key: string,
     *     name?: string,
     *     platform?: string
     * }  $data
     */
    public function execute(User $user, array $data, Request $request): UserDevice
    {
        if (! $this->tokenService->verifyPassword($user, $data['password'])) {
            throw ValidationException::withMessages([
                'password' => ['Mot de passe incorrect.'],
            ]);
        }

        $this->otpService->assertValid($user, $data['otp']);

        $backup = DeviceRecoveryBackup::query()->where('user_id', $user->id)->first();
        if ($backup === null) {
            throw ValidationException::withMessages([
                'phrase' => ['Aucune phrase de récupération n’est enregistrée pour ce compte.'],
            ]);
        }

        $fingerprint = hash('sha256', $data['public_key']);
        if (! hash_equals($backup->public_key_fingerprint, $fingerprint)) {
            throw ValidationException::withMessages([
                'public_key' => ['La clé restaurée ne correspond pas au backup de récupération.'],
            ]);
        }

        $target = UserDevice::query()
            ->where('user_id', $user->id)
            ->where('fingerprint', $fingerprint)
            ->first();

        if ($target === null) {
            $target = UserDevice::query()->create([
                'user_id' => $user->id,
                'name' => $data['name'] ?? 'Appareil restauré',
                'platform' => $data['platform'] ?? DevicePlatform::Web->value,
                'public_key' => $data['public_key'],
                'fingerprint' => $fingerprint,
                'status' => DeviceStatus::Pending,
                'last_seen_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);
        } else {
            $target->forceFill([
                'name' => $data['name'] ?? $target->name,
                'platform' => $data['platform'] ?? $target->platform,
                'public_key' => $data['public_key'],
            ])->save();
        }

        $device = $this->promoteRecoveredDeviceAction->execute($user, $target->fresh(), $request, [
            'mode' => 'phrase',
            'history_preserved' => true,
        ]);

        $this->otpService->clear($user);

        return $device;
    }
}
