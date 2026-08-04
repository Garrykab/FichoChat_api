<?php

namespace Tests\Feature\Settings;

use App\Enums\ActivityLogType;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_are_created_with_user(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('user_settings', [
            'user_id' => $user->id,
            'notifications_enabled' => true,
            'silent_mode' => false,
        ]);
    }

    public function test_user_can_fetch_settings(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.settings.notifications_enabled', true)
            ->assertJsonPath('data.appearance.locale', 'fr')
            ->assertJsonPath('data.appearance.theme', 'system');
    }

    public function test_user_can_update_notification_and_privacy_settings(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);

        $this->withToken($token)
            ->patchJson('/api/v1/settings', [
                'notifications_enabled' => true,
                'notify_devices' => false,
                'hide_message_previews' => true,
                'silent_mode' => true,
                'send_read_receipts' => false,
                'show_last_seen' => false,
                'auto_download_media' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.settings.notify_devices', false)
            ->assertJsonPath('data.settings.hide_message_previews', true)
            ->assertJsonPath('data.settings.silent_mode', true)
            ->assertJsonPath('data.settings.send_read_receipts', false)
            ->assertJsonPath('data.settings.show_last_seen', false)
            ->assertJsonPath('data.settings.auto_download_media', true);

        $this->assertDatabaseHas('user_settings', [
            'user_id' => $user->id,
            'silent_mode' => true,
            'send_read_receipts' => false,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'causer_id' => $user->id,
            'event' => ActivityLogType::SettingsUpdated->value,
        ]);
    }

    public function test_user_can_update_appearance_via_settings(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);

        $this->withToken($token)
            ->patchJson('/api/v1/settings', [
                'theme' => 'dark',
                'locale' => 'en',
            ])
            ->assertOk()
            ->assertJsonPath('data.appearance.theme', 'dark')
            ->assertJsonPath('data.appearance.locale', 'en');

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'theme' => 'dark',
            'locale' => 'en',
        ]);
    }

    public function test_settings_validation_rejects_invalid_theme(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);

        $this->withToken($token)
            ->patchJson('/api/v1/settings', [
                'theme' => 'neon',
            ])
            ->assertStatus(422);
    }

    public function test_register_creates_default_settings(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'email' => 'settings@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'terms_accepted' => true,
        ])->assertCreated();

        $user = User::query()->where('email', 'settings@example.com')->firstOrFail();

        $this->assertTrue(
            UserSetting::query()->where('user_id', $user->id)->exists(),
        );
    }

    private function loginToken(User $user): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.tokens.access_token');
    }
}
