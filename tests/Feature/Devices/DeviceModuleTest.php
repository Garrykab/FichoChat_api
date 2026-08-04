<?php

namespace Tests\Feature\Devices;

use App\Enums\DeviceStatus;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_device_is_auto_approved(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);

        $response = $this->withToken($token)
            ->postJson('/api/v1/devices', [
                'name' => 'MacBook',
                'platform' => 'web',
                'public_key' => $this->publicKey('device-1'),
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.device.status', DeviceStatus::Approved->value);

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user->id,
            'status' => DeviceStatus::Approved->value,
        ]);
    }

    public function test_second_device_is_pending_until_approved(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);

        $first = $this->withToken($token)
            ->postJson('/api/v1/devices', [
                'name' => 'MacBook',
                'platform' => 'web',
                'public_key' => $this->publicKey('device-1'),
            ])->json('data.device');

        $second = $this->withToken($token)
            ->postJson('/api/v1/devices', [
                'name' => 'iPhone',
                'platform' => 'ios',
                'public_key' => $this->publicKey('device-2'),
            ]);

        $second->assertCreated()
            ->assertJsonPath('data.device.status', DeviceStatus::Pending->value);

        $this->withToken($token)
            ->postJson('/api/v1/devices/'.$second->json('data.device.id').'/approve', [
                'actor_device_id' => $first['id'],
            ])
            ->assertOk()
            ->assertJsonPath('data.device.status', DeviceStatus::Approved->value)
            ->assertJsonPath('data.device.approved_by_device_id', $first['id']);
    }

    public function test_pending_devices_endpoint(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);

        $this->withToken($token)->postJson('/api/v1/devices', [
            'name' => 'MacBook',
            'platform' => 'web',
            'public_key' => $this->publicKey('device-1'),
        ]);

        $this->withToken($token)->postJson('/api/v1/devices', [
            'name' => 'iPhone',
            'platform' => 'ios',
            'public_key' => $this->publicKey('device-2'),
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/devices/pending')
            ->assertOk()
            ->assertJsonCount(1, 'data.devices')
            ->assertJsonPath('data.devices.0.status', DeviceStatus::Pending->value);
    }

    public function test_revoked_device_cannot_login(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);

        $device = $this->withToken($token)
            ->postJson('/api/v1/devices', [
                'name' => 'MacBook',
                'platform' => 'web',
                'public_key' => $this->publicKey('device-1'),
            ])->json('data.device');

        $this->withToken($token)
            ->postJson('/api/v1/devices/'.$device['id'].'/revoke', [
                'actor_device_id' => $device['id'],
            ])
            ->assertOk()
            ->assertJsonPath('data.device.status', DeviceStatus::Revoked->value);

        $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
            'device_id' => $device['id'],
        ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_user_can_list_devices(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);

        $this->withToken($token)->postJson('/api/v1/devices', [
            'name' => 'MacBook',
            'platform' => 'web',
            'public_key' => $this->publicKey('device-1'),
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/devices')
            ->assertOk()
            ->assertJsonCount(1, 'data.devices');
    }

    public function test_revoked_device_cannot_be_reregistered_with_same_key(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);
        $publicKey = $this->publicKey('same-key');

        $device = $this->withToken($token)
            ->postJson('/api/v1/devices', [
                'name' => 'MacBook',
                'platform' => 'web',
                'public_key' => $publicKey,
            ])->json('data.device');

        $this->withToken($token)->postJson('/api/v1/devices/'.$device['id'].'/revoke', [
            'actor_device_id' => $device['id'],
        ])->assertOk();

        $this->withToken($token)
            ->postJson('/api/v1/devices', [
                'name' => 'MacBook 2',
                'platform' => 'web',
                'public_key' => $publicKey,
            ])
            ->assertStatus(422);
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
