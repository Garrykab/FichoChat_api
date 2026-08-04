<?php

namespace App\Actions\Keys;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RotateDeviceKeyAction
{
    /**
     * @param  array{public_key: string, actor_device_id: string}  $data
     */
    public function execute(User $user, UserDevice $device, array $data, Request $request): UserDevice
    {
        if ($device->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'device' => ['You can only rotate keys for your own devices.'],
            ]);
        }

        if ($data['actor_device_id'] !== $device->id) {
            throw ValidationException::withMessages([
                'actor_device_id' => ['Only the device itself can rotate its key.'],
            ]);
        }

        if (! $device->isApproved()) {
            throw ValidationException::withMessages([
                'device' => ['Only approved devices can rotate keys.'],
            ]);
        }

        $fingerprint = hash('sha256', $data['public_key']);

        $collision = UserDevice::query()
            ->where('user_id', $user->id)
            ->where('fingerprint', $fingerprint)
            ->where('id', '!=', $device->id)
            ->exists();

        if ($collision) {
            throw ValidationException::withMessages([
                'public_key' => ['This public key is already registered on another device.'],
            ]);
        }

        $device->forceFill([
            'public_key' => $data['public_key'],
            'fingerprint' => $fingerprint,
            'last_seen_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ])->save();

        return $device->fresh();
    }
}
