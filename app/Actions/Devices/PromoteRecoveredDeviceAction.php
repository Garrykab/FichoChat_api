<?php

namespace App\Actions\Devices;

use App\Actions\Sync\EnsureDeviceSyncStateAction;
use App\Enums\DeviceStatus;
use App\Enums\SecurityEventType;
use App\Events\DeviceStatusChanged;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\TokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromoteRecoveredDeviceAction
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly EnsureDeviceSyncStateAction $ensureDeviceSyncStateAction,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Révoque tous les autres appareils, approuve (ou réactive) la cible, invalide les refresh tokens.
     *
     * @param  array{mode: string, history_preserved?: bool}  $meta
     */
    public function execute(User $user, UserDevice $target, Request $request, array $meta): UserDevice
    {
        $device = DB::transaction(function () use ($user, $target, $request) {
            $others = UserDevice::query()
                ->where('user_id', $user->id)
                ->where('id', '!=', $target->id)
                ->where('status', '!=', DeviceStatus::Revoked)
                ->get();

            foreach ($others as $other) {
                $other->forceFill([
                    'status' => DeviceStatus::Revoked,
                    'revoked_at' => now(),
                ])->save();
                $this->tokenService->revokeAllForDevice($other->id);
                DeviceStatusChanged::dispatch($other->fresh(), 'revoked');
            }

            $target->forceFill([
                'status' => DeviceStatus::Approved,
                'approved_at' => $target->approved_at ?? now(),
                'approved_by_device_id' => null,
                'revoked_at' => null,
                'last_seen_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ])->save();

            $this->tokenService->revokeAllForDevice($target->id);
            $this->ensureDeviceSyncStateAction->execute($target->fresh());

            return $target->fresh();
        });

        DeviceStatusChanged::dispatch($device, 'approved');

        $this->auditLogger->security(
            SecurityEventType::DeviceRecovered,
            $user,
            [
                'device_id' => $device->id,
                'mode' => $meta['mode'],
                'history_preserved' => (bool) ($meta['history_preserved'] ?? false),
            ],
            deviceId: $device->id,
            request: $request,
        );

        return $device;
    }
}
