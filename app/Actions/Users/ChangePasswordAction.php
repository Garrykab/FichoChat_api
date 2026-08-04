<?php

namespace App\Actions\Users;

use App\Enums\SecurityEventType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\TokenService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChangePasswordAction
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(User $user, string $currentPassword, string $newPassword, bool $revokeOtherSessions = true): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill([
            'password' => $newPassword,
        ])->save();

        if ($revokeOtherSessions) {
            $this->tokenService->revokeAllForUser($user);
        }

        $this->auditLogger->security(
            SecurityEventType::PasswordChanged,
            $user,
            ['revoke_other_sessions' => $revokeOtherSessions],
        );
    }
}
