<?php

namespace App\Actions\Devices;

use App\Enums\SecurityEventType;
use App\Models\User;
use App\Notifications\DeviceRecoveryOtpNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Devices\DeviceRecoveryOtpService;
use Illuminate\Http\Request;

class RequestDeviceRecoveryOtpAction
{
    public function __construct(
        private readonly DeviceRecoveryOtpService $otpService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(User $user, Request $request): void
    {
        $code = $this->otpService->issue($user);
        $user->notify(new DeviceRecoveryOtpNotification($code));

        $this->auditLogger->security(
            SecurityEventType::DeviceRecoveryOtpSent,
            $user,
            ['email' => $user->email],
            request: $request,
        );
    }
}
