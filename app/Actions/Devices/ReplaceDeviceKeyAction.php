<?php

namespace App\Actions\Devices;

use App\Enums\SecurityEventType;
use App\Models\UserDevice;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReplaceDeviceKeyAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Remplace la clé publique d’un appareil approved (même id) — pour activer une phrase de recovery.
     *
     * @param  array{public_key: string}  $data
     */
    public function execute(UserDevice $device, array $data, Request $request): UserDevice
    {
        if (! $device->isApproved()) {
            throw ValidationException::withMessages([
                'device' => ['Seul un appareil approuvé peut remplacer sa clé.'],
            ]);
        }

        $fingerprint = hash('sha256', $data['public_key']);

        $collision = UserDevice::query()
            ->where('user_id', $device->user_id)
            ->where('fingerprint', $fingerprint)
            ->where('id', '!=', $device->id)
            ->exists();

        if ($collision) {
            throw ValidationException::withMessages([
                'public_key' => ['Cette clé est déjà utilisée par un autre appareil.'],
            ]);
        }

        $updated = DB::transaction(function () use ($device, $data, $fingerprint, $request) {
            $device->forceFill([
                'public_key' => $data['public_key'],
                'fingerprint' => $fingerprint,
                'last_seen_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ])->save();

            return $device->fresh();
        });

        $this->auditLogger->security(
            SecurityEventType::DeviceRegistered,
            $device->user,
            [
                'device_id' => $updated->id,
                'action' => 'replace_key',
                'fingerprint' => $updated->fingerprint,
            ],
            deviceId: $updated->id,
            request: $request,
        );

        return $updated;
    }
}
