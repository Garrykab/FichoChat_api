<?php

namespace Tests\Feature\Devices;

use App\Enums\DeviceStatus;
use App\Enums\SecurityEventType;
use App\Models\DeviceRecoveryBackup;
use App\Models\User;
use App\Models\UserDevice;
use App\Notifications\DeviceRecoveryOtpNotification;
use App\Services\Devices\DeviceRecoveryOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DeviceRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_recovery_approves_pending_and_revokes_others(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $token = $this->loginToken($user);

        $first = $this->withToken($token)->postJson('/api/v1/devices', [
            'name' => 'Old',
            'platform' => 'web',
            'public_key' => $this->publicKey('old'),
        ])->json('data.device');

        $pending = $this->withToken($token)->postJson('/api/v1/devices', [
            'name' => 'New',
            'platform' => 'web',
            'public_key' => $this->publicKey('new'),
        ])->json('data.device');

        $this->withToken($token)
            ->postJson('/api/v1/devices/recovery/request-otp')
            ->assertOk();

        Notification::assertSentTo($user, DeviceRecoveryOtpNotification::class);
        $code = $this->otpFor($user);

        $this->withToken($token)
            ->postJson('/api/v1/devices/recovery/confirm', [
                'password' => 'password',
                'otp' => $code,
                'device_id' => $pending['id'],
            ])
            ->assertOk()
            ->assertJsonPath('data.device.status', DeviceStatus::Approved->value)
            ->assertJsonPath('data.history_preserved', false);

        $this->assertDatabaseHas('user_devices', [
            'id' => $first['id'],
            'status' => DeviceStatus::Revoked->value,
        ]);
        $this->assertDatabaseHas('user_devices', [
            'id' => $pending['id'],
            'status' => DeviceStatus::Approved->value,
        ]);
        $this->assertDatabaseHas('security_events', [
            'type' => SecurityEventType::DeviceRecovered->value,
        ]);
    }

    public function test_recovery_backup_roundtrip_and_phrase_restore(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $token = $this->loginToken($user);
        $publicKey = $this->publicKey('recoverable');

        $device = $this->withToken($token)->postJson('/api/v1/devices', [
            'name' => 'Primary',
            'platform' => 'web',
            'public_key' => $publicKey,
        ])->json('data.device');

        $this->withToken($token)
            ->putJson('/api/v1/devices/recovery/backup', [
                'ciphertext' => 'cipher',
                'salt' => 'saltvalue',
                'iv' => 'ivvalue',
                'kdf' => 'pbkdf2-sha256',
                'kdf_iterations' => 310000,
                'public_key_fingerprint' => hash('sha256', $publicKey),
                'version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.has_backup', true);

        $this->withToken($token)
            ->getJson('/api/v1/devices/recovery/backup')
            ->assertOk()
            ->assertJsonPath('data.has_backup', true)
            ->assertJsonPath('data.backup.ciphertext', 'cipher');

        $this->withToken($token)->postJson('/api/v1/devices/'.$device['id'].'/revoke', [
            'actor_device_id' => $device['id'],
        ])->assertOk();

        $this->assertDatabaseHas('user_devices', [
            'id' => $device['id'],
            'status' => DeviceStatus::Revoked->value,
        ]);

        $this->withToken($token)->postJson('/api/v1/devices/recovery/request-otp')->assertOk();
        $code = $this->otpFor($user);

        $this->withToken($token)
            ->postJson('/api/v1/devices/recovery/restore', [
                'password' => 'password',
                'otp' => $code,
                'public_key' => $publicKey,
                'name' => 'Restored',
                'platform' => 'web',
            ])
            ->assertOk()
            ->assertJsonPath('data.device.status', DeviceStatus::Approved->value)
            ->assertJsonPath('data.device.id', $device['id'])
            ->assertJsonPath('data.history_preserved', true);

        $this->assertDatabaseHas('user_devices', [
            'id' => $device['id'],
            'status' => DeviceStatus::Approved->value,
            'revoked_at' => null,
        ]);
    }

    public function test_phrase_restore_rejects_wrong_fingerprint(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $token = $this->loginToken($user);
        $publicKey = $this->publicKey('backed');

        $this->withToken($token)->postJson('/api/v1/devices', [
            'name' => 'Primary',
            'platform' => 'web',
            'public_key' => $publicKey,
        ]);

        DeviceRecoveryBackup::query()->create([
            'user_id' => $user->id,
            'ciphertext' => 'x',
            'salt' => 's',
            'iv' => 'i',
            'kdf' => 'pbkdf2-sha256',
            'kdf_iterations' => 310000,
            'public_key_fingerprint' => hash('sha256', $publicKey),
            'version' => 1,
        ]);

        $this->withToken($token)->postJson('/api/v1/devices/recovery/request-otp')->assertOk();
        $code = $this->otpFor($user);

        $this->withToken($token)
            ->postJson('/api/v1/devices/recovery/restore', [
                'password' => 'password',
                'otp' => $code,
                'public_key' => $this->publicKey('other'),
            ])
            ->assertStatus(422);
    }

    public function test_pending_device_cannot_upsert_backup(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);

        $this->withToken($token)->postJson('/api/v1/devices', [
            'name' => 'First',
            'platform' => 'web',
            'public_key' => $this->publicKey('first'),
        ]);

        $this->withToken($token)->postJson('/api/v1/devices', [
            'name' => 'Second',
            'platform' => 'web',
            'public_key' => $this->publicKey('second'),
        ]);

        // Second is pending; revoke first so only pending remains → no approved
        $first = UserDevice::query()->where('user_id', $user->id)->where('status', DeviceStatus::Approved)->first();
        $first->forceFill([
            'status' => DeviceStatus::Revoked,
            'revoked_at' => now(),
        ])->save();

        $this->withToken($token)
            ->putJson('/api/v1/devices/recovery/backup', [
                'ciphertext' => 'cipher',
                'salt' => 'saltvalue',
                'iv' => 'ivvalue',
                'kdf' => 'pbkdf2-sha256',
                'kdf_iterations' => 310000,
                'public_key_fingerprint' => str_repeat('a', 64),
            ])
            ->assertStatus(422);
    }

    private function otpFor(User $user): string
    {
        /** @var DeviceRecoveryOtpService $otp */
        $otp = app(DeviceRecoveryOtpService::class);
        // Re-issue to get a known code after notification (notification already has one).
        // Instead read from notification:
        $notification = Notification::sent($user, DeviceRecoveryOtpNotification::class)->first();
        $this->assertNotNull($notification);

        return $notification->code;
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
