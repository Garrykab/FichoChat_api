<?php

namespace App\Actions\Devices;

use App\Actions\Sync\EnsureDeviceSyncStateAction;
use App\Enums\DeviceStatus;
use App\Enums\SecurityEventType;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterDeviceAction
{
    public function __construct(
        private readonly EnsureDeviceSyncStateAction $ensureDeviceSyncStateAction,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{name: string, platform: string, public_key: string}  $data
     */
    public function execute(User $user, array $data, Request $request): UserDevice
    {
        $fingerprint = hash('sha256', $data['public_key']);

        $existing = UserDevice::query()
            ->where('user_id', $user->id)
            ->where('fingerprint', $fingerprint)
            ->first();

        if ($existing !== null) {
            if ($existing->isRevoked()) {
                throw ValidationException::withMessages([
                    'public_key' => ['This device was revoked and cannot be re-registered with the same key.'],
                ]);
            }

            $existing->forceFill([
                'name' => $data['name'],
                'platform' => $data['platform'],
                'last_seen_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ])->save();

            if ($existing->isApproved()) {
                $this->ensureDeviceSyncStateAction->execute($existing);
            }

            return $existing->fresh();
        }

        $device = DB::transaction(function () use ($user, $data, $request, $fingerprint) {
            $hasApprovedDevice = UserDevice::query()
                ->where('user_id', $user->id)
                ->where('status', DeviceStatus::Approved)
                ->exists();

            $status = $hasApprovedDevice ? DeviceStatus::Pending : DeviceStatus::Approved;

            $device = UserDevice::query()->create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'platform' => $data['platform'],
                'public_key' => $data['public_key'],
                'fingerprint' => $fingerprint,
                'status' => $status,
                'approved_at' => $status === DeviceStatus::Approved ? now() : null,
                'last_seen_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);

            if ($status === DeviceStatus::Approved) {
                $this->ensureDeviceSyncStateAction->execute($device);
            }

            return $device;
        });

        $this->auditLogger->security(
            SecurityEventType::DeviceRegistered,
            $user,
            [
                'device_id' => $device->id,
                'status' => $device->status?->value,
                'fingerprint' => $device->fingerprint,
            ],
            deviceId: $device->id,
            request: $request,
        );

        if ($device->isPending()) {
            \App\Events\DeviceStatusChanged::dispatch($device, 'pending');
        }

        return $device;
    }
}
