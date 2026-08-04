<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_cannot_access_users_endpoints(): void
    {
        $user = User::factory()->unverified()->create();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.tokens.access_token');

        $this->withToken($token)
            ->getJson('/api/v1/users/me')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Email address is not verified.');
    }

    public function test_unverified_user_cannot_access_devices_endpoints(): void
    {
        $user = User::factory()->unverified()->create();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.tokens.access_token');

        $this->withToken($token)
            ->getJson('/api/v1/devices')
            ->assertForbidden()
            ->assertJsonPath('message', 'Email address is not verified.');
    }

    public function test_unverified_user_can_resend_verification_email(): void
    {
        $user = User::factory()->unverified()->create();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.tokens.access_token');

        $this->withToken($token)
            ->postJson('/api/v1/auth/email/resend')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
