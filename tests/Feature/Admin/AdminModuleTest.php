<?php

namespace Tests\Feature\Admin;

use App\Enums\DeviceStatus;
use App\Enums\SecurityEventType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/admin/stats')
            ->assertForbidden();

        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'type' => SecurityEventType::UnauthorizedAccess->value,
        ]);
    }

    public function test_admin_can_view_stats(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(2)->create();
        $token = $this->loginToken($admin);

        $this->withToken($token)
            ->getJson('/api/v1/admin/stats')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.users.total', 3)
            ->assertJsonStructure([
                'data' => [
                    'users',
                    'devices',
                    'conversations',
                    'messages',
                    'medias',
                    'login_failures_24h',
                ],
            ]);
    }

    public function test_admin_can_list_and_suspend_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $token = $this->loginToken($admin);

        $this->withToken($token)
            ->getJson('/api/v1/admin/users?q='.$target->username)
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);

        $this->withToken($token)
            ->postJson('/api/v1/admin/users/'.$target->id.'/suspend', [
                'reason' => 'spam',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.status', UserStatus::Suspended->value);

        $this->assertDatabaseHas('security_events', [
            'user_id' => $admin->id,
            'type' => SecurityEventType::UserSuspended->value,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => $target->email,
            'password' => 'password',
        ])->assertStatus(422);
    }

    public function test_admin_cannot_suspend_self_or_other_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $token = $this->loginToken($admin);

        $this->withToken($token)
            ->postJson('/api/v1/admin/users/'.$admin->id.'/suspend')
            ->assertStatus(422);

        $this->withToken($token)
            ->postJson('/api/v1/admin/users/'.$otherAdmin->id.'/suspend')
            ->assertStatus(422);
    }

    public function test_admin_can_activate_suspended_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->suspended()->create();
        $token = $this->loginToken($admin);

        $this->withToken($token)
            ->postJson('/api/v1/admin/users/'.$target->id.'/activate')
            ->assertOk()
            ->assertJsonPath('data.user.status', UserStatus::Active->value);

        $this->assertDatabaseHas('security_events', [
            'type' => SecurityEventType::UserActivated->value,
        ]);
    }

    public function test_admin_can_revoke_device(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $device = UserDevice::query()->create([
            'user_id' => $user->id,
            'name' => 'Phone',
            'platform' => 'ios',
            'public_key' => $this->publicKey('adm-dev'),
            'fingerprint' => hash('sha256', $this->publicKey('adm-dev')),
            'status' => DeviceStatus::Approved,
            'approved_at' => now(),
            'last_seen_at' => now(),
        ]);

        $token = $this->loginToken($admin);

        $this->withToken($token)
            ->postJson('/api/v1/admin/devices/'.$device->id.'/revoke')
            ->assertOk()
            ->assertJsonPath('data.device.status', DeviceStatus::Revoked->value);

        $this->assertDatabaseHas('security_events', [
            'type' => SecurityEventType::AdminDeviceRevoked->value,
        ]);
    }

    public function test_admin_can_list_global_security_events(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $this->loginToken($user);
        $token = $this->loginToken($admin);

        $this->withToken($token)
            ->getJson('/api/v1/admin/security/events')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertGreaterThan(0, count(
            $this->withToken($token)->getJson('/api/v1/admin/security/events')->json('data.events'),
        ));
    }

    public function test_admin_emails_config_promotes_on_login(): void
    {
        config(['fichochat.admin_emails' => ['promote@example.com']]);

        $user = User::factory()->create([
            'email' => 'promote@example.com',
        ]);

        $this->assertFalse($user->isAdmin());

        $this->postJson('/api/v1/auth/login', [
            'login' => 'promote@example.com',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.role', 'admin');

        $this->assertTrue($user->fresh()->isAdmin());
    }

    public function test_admin_can_read_and_update_media_settings(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $this->loginToken($admin);

        $this->withToken($token)
            ->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.media.max_files', 10)
            ->assertJsonPath('data.media.max_file_bytes', 52_428_800);

        $this->withToken($token)
            ->patchJson('/api/v1/admin/settings', [
                'media' => [
                    'max_files' => 5,
                    'max_file_bytes' => 10 * 1024 * 1024,
                    'max_total_bytes' => 20 * 1024 * 1024,
                    'chunk_bytes' => 512 * 1024,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.media.max_files', 5)
            ->assertJsonPath('data.media.max_file_bytes', 10_485_760);

        $user = User::factory()->create();
        $userToken = $this->loginToken($user);

        $this->withToken($userToken)
            ->getJson('/api/v1/media/limits')
            ->assertOk()
            ->assertJsonPath('data.limits.max_files', 5)
            ->assertJsonPath('data.limits.chunk_bytes', 524_288);
    }

    public function test_admin_without_settings_permission_is_forbidden(): void
    {
        $adminRole = \Spatie\Permission\Models\Role::findByName('admin', 'api');
        $adminRole->revokePermissionTo(['admin.settings.view', 'admin.settings.update']);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = User::factory()->admin()->create();
        $token = $this->loginToken($admin);

        $this->withToken($token)
            ->getJson('/api/v1/admin/settings')
            ->assertForbidden();

        $this->withToken($token)
            ->patchJson('/api/v1/admin/settings', [
                'media' => ['max_files' => 3],
            ])
            ->assertForbidden();
    }

    private function loginToken(User $user): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.tokens.access_token');
    }

    private function publicKey(string $seed): string
    {
        return "-----BEGIN PUBLIC KEY-----\n".base64_encode(str_pad($seed, 64, 'x'))."\n-----END PUBLIC KEY-----";
    }
}
