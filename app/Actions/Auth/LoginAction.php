<?php

namespace App\Actions\Auth;

use App\Actions\Admin\PromoteAdminFromConfigAction;
use App\Enums\DeviceStatus;
use App\Enums\LogSeverity;
use App\Enums\SecurityEventType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\TokenService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoginAction
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly AuditLogger $auditLogger,
        private readonly PromoteAdminFromConfigAction $promoteAdminFromConfigAction,
    ) {}

    /**
     * @param  array{login: string, password: string, device_id?: string|null}  $data
     * @return array{user: User, tokens: array<string, mixed>, device: UserDevice|null}
     */
    public function execute(array $data, Request $request): array
    {
        $user = User::query()
            ->where(function ($query) use ($data): void {
                $query->where('email', $data['login'])
                    ->orWhere('username', $data['login']);
            })
            ->first();

        if ($user === null || ! $this->tokenService->verifyPassword($user, $data['password'])) {
            $this->auditLogger->security(
                SecurityEventType::LoginFailed,
                $user,
                ['login' => $data['login']],
                LogSeverity::Warning,
                'failure',
                null,
                'auth',
                $request,
            );

            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status === UserStatus::Suspended) {
            $this->auditLogger->security(
                SecurityEventType::LoginFailed,
                $user,
                ['reason' => 'suspended'],
                LogSeverity::Warning,
                'failure',
                null,
                'auth',
                $request,
            );

            throw ValidationException::withMessages([
                'login' => ['This account has been suspended.'],
            ]);
        }

        $device = null;
        $deviceId = $data['device_id'] ?? null;

        if ($deviceId !== null) {
            $device = UserDevice::query()
                ->where('id', $deviceId)
                ->where('user_id', $user->id)
                ->first();

            if ($device === null) {
                throw ValidationException::withMessages([
                    'device_id' => ['The device does not belong to this account.'],
                ]);
            }

            if ($device->status === DeviceStatus::Revoked) {
                $this->auditLogger->security(
                    SecurityEventType::UnauthorizedAccess,
                    $user,
                    ['reason' => 'revoked_device', 'device_id' => $device->id],
                    LogSeverity::Warning,
                    'failure',
                    $device->id,
                    'auth',
                    $request,
                );

                throw ValidationException::withMessages([
                    'device_id' => ['This device has been revoked.'],
                ]);
            }

            if ($device->status === DeviceStatus::Pending) {
                throw ValidationException::withMessages([
                    'device_id' => ['This device is pending approval.'],
                ]);
            }

            $device->forceFill(['last_seen_at' => now()])->save();
        }

        $tokens = $this->tokenService->issueTokenPair(
            $user,
            $request,
            $device?->id,
        );

        $this->auditLogger->security(
            SecurityEventType::Login,
            $user,
            [],
            LogSeverity::Info,
            'success',
            $device?->id,
            'auth',
            $request,
        );

        $user = $this->promoteAdminFromConfigAction->execute($user);

        return [
            'user' => $user,
            'tokens' => $tokens,
            'device' => $device,
        ];
    }
}
