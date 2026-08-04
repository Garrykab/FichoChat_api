<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'alice@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'terms_accepted' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'alice@example.com')
            ->assertJsonPath('data.user.profile_setup_completed', false)
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'username', 'email', 'status', 'profile_setup_completed'],
                    'tokens' => ['access_token', 'refresh_token', 'token_type', 'expires_in'],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'alice@example.com',
            'status' => UserStatus::Pending->value,
        ]);

        $this->assertNotNull(User::first()?->terms_accepted_at);
        $this->assertNull(User::first()?->profile_setup_completed_at);

        Notification::assertSentTo(User::first(), VerifyEmailNotification::class);
    }

    public function test_register_requires_terms_acceptance(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'email' => 'alice@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'terms_accepted' => false,
        ])->assertStatus(422);
    }

    public function test_user_can_login_with_email(): void
    {
        $user = User::factory()->create([
            'email' => 'bob@example.com',
            'username' => 'bob',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'bob@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure([
                'data' => [
                    'tokens' => ['access_token', 'refresh_token'],
                ],
            ]);
    }

    public function test_user_can_login_with_username(): void
    {
        User::factory()->create([
            'username' => 'carol',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'carol',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('data.user.username', 'carol');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create();

        $this->postJson('/api/v1/auth/login', [
            'login' => 'unknown@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::factory()->create();

        $login = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.tokens.access_token');

        $this->withToken($login)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_user_can_refresh_tokens(): void
    {
        $user = User::factory()->create();

        $tokens = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.tokens');

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $tokens['refresh_token'],
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'tokens' => ['access_token', 'refresh_token'],
                ],
            ]);

        $this->assertNotSame(
            $tokens['refresh_token'],
            $response->json('data.tokens.refresh_token'),
        );
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $tokens = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.tokens');

        $this->postJson('/api/v1/auth/logout', [
            'refresh_token' => $tokens['refresh_token'],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $tokens['refresh_token'],
        ])->assertStatus(422);
    }

    public function test_web_client_gets_refresh_cookie_without_body_token(): void
    {
        $this->disableCookieEncryption();

        $user = User::factory()->create();
        $cookieName = config('auth.refresh_cookie.name');

        $response = $this->withHeaders(['X-Client' => 'web'])
            ->postJson('/api/v1/auth/login', [
                'login' => $user->email,
                'password' => 'password',
            ]);

        $response->assertOk()
            ->assertJsonMissingPath('data.tokens.refresh_token')
            ->assertJsonStructure([
                'data' => [
                    'tokens' => ['access_token', 'token_type', 'expires_in'],
                ],
            ])
            ->assertCookie($cookieName);

        $refresh = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === $cookieName)
            ?->getValue();

        $this->assertNotEmpty($refresh);
        $this->assertDatabaseHas('refresh_tokens', [
            'token_hash' => hash('sha256', (string) $refresh),
        ]);

        $refreshed = $this->call(
            'POST',
            '/api/v1/auth/refresh',
            [],
            [$cookieName => $refresh],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CLIENT' => 'web',
                'HTTP_ORIGIN' => (string) config('app.frontend_url'),
                'CONTENT_TYPE' => 'application/json',
            ],
        );

        $refreshed->assertOk()
            ->assertJsonMissingPath('data.tokens.refresh_token')
            ->assertJsonStructure([
                'data' => [
                    'tokens' => ['access_token'],
                ],
            ]);
    }

    public function test_user_can_verify_email(): void
    {
        $user = User::factory()->unverified()->create();

        $this->postJson('/api/v1/auth/email/verify', [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ])->assertOk()
            ->assertJsonPath('data.user.status', UserStatus::Active->value);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_forgot_password_sends_notification_when_user_exists(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_user_can_reset_password(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'NewPassword1!',
        ])->assertOk();
    }
}
