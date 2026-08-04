<?php

namespace App\Actions\Auth;

use App\Actions\Users\EnsureUserProfileAction;
use App\Actions\Users\EnsureUserSettingsAction;
use App\Enums\SecurityEventType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\TokenService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterUserAction
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly EnsureUserProfileAction $ensureUserProfileAction,
        private readonly EnsureUserSettingsAction $ensureUserSettingsAction,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{email: string, password: string}  $data
     * @return array{user: User, tokens: array<string, mixed>}
     */
    public function execute(array $data, Request $request): array
    {
        $result = DB::transaction(function () use ($data, $request) {
            $user = User::query()->create([
                'username' => $this->provisionalUsername(),
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => UserStatus::Pending,
                'terms_accepted_at' => now(),
                'profile_setup_completed_at' => null,
            ]);

            $this->ensureUserProfileAction->execute($user);
            $this->ensureUserSettingsAction->execute($user);
            $user->assignRole('user');

            event(new Registered($user));

            $tokens = $this->tokenService->issueTokenPair($user, $request);

            return [
                'user' => $user->load(['profile', 'settings']),
                'tokens' => $tokens,
            ];
        });

        $this->auditLogger->security(
            SecurityEventType::Register,
            $result['user'],
            ['email' => $result['user']->email],
            request: $request,
        );

        return $result;
    }

    private function provisionalUsername(): string
    {
        do {
            $username = 'tmp_'.Str::lower(Str::random(12));
        } while (User::query()->where('username', $username)->exists());

        return $username;
    }
}
