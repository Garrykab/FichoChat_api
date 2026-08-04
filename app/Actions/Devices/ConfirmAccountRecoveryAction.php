<?php

namespace App\Actions\Devices;

use App\Models\User;
use App\Models\UserDevice;
use App\Services\Auth\TokenService;
use App\Services\Devices\DeviceRecoveryOtpService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConfirmAccountRecoveryAction
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly DeviceRecoveryOtpService $otpService,
        private readonly PromoteRecoveredDeviceAction $promoteRecoveredDeviceAction,
    ) {}

    /**
     * V1 — récupération sans phrase : historique chiffré perdu.
     *
     * @param  array{password: string, otp: string, device_id: string}  $data
     */
    public function execute(User $user, array $data, Request $request): UserDevice
    {
        if (! $this->tokenService->verifyPassword($user, $data['password'])) {
            throw ValidationException::withMessages([
                'password' => ['Mot de passe incorrect.'],
            ]);
        }

        $this->otpService->assertValid($user, $data['otp']);

        $target = UserDevice::query()
            ->where('id', $data['device_id'])
            ->where('user_id', $user->id)
            ->first();

        if ($target === null) {
            throw ValidationException::withMessages([
                'device_id' => ['Appareil introuvable.'],
            ]);
        }

        if ($target->isRevoked()) {
            throw ValidationException::withMessages([
                'device_id' => ['Cet appareil est révoqué. Enregistrez-en un nouveau puis réessayez.'],
            ]);
        }

        $device = $this->promoteRecoveredDeviceAction->execute($user, $target, $request, [
            'mode' => 'account',
            'history_preserved' => false,
        ]);

        $this->otpService->clear($user);

        return $device;
    }
}
