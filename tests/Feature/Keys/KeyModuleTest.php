<?php

namespace Tests\Feature\Keys;

use App\Enums\DeviceStatus;
use App\Enums\EnvelopeContentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KeyModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_own_approved_public_keys(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);
        $this->registerDevice($token, 'device-1');

        $this->withToken($token)
            ->getJson('/api/v1/keys/me/public')
            ->assertOk()
            ->assertJsonCount(1, 'data.devices')
            ->assertJsonPath('data.devices.0.status', DeviceStatus::Approved->value)
            ->assertJsonStructure([
                'data' => [
                    'devices' => [
                        ['device_id', 'public_key', 'fingerprint'],
                    ],
                ],
            ]);
    }

    public function test_user_can_fetch_another_users_approved_public_keys(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $aliceToken = $this->loginToken($alice);
        $bobToken = $this->loginToken($bob);

        $this->registerDevice($bobToken, 'bob-device');

        $this->withToken($aliceToken)
            ->getJson('/api/v1/keys/users/'.$bob->id.'/public')
            ->assertOk()
            ->assertJsonCount(1, 'data.devices')
            ->assertJsonPath('data.devices.0.user_id', $bob->id);
    }

    public function test_user_can_store_and_list_key_envelopes(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);
        $sender = $this->registerDevice($token, 'sender');
        $recipient = $this->registerDevice($token, 'recipient');

        // Second device is pending — approve it
        $this->withToken($token)
            ->postJson('/api/v1/devices/'.$recipient['id'].'/approve', [
                'actor_device_id' => $sender['id'],
            ])
            ->assertOk();

        $contentId = (string) Str::uuid();

        $this->withToken($token)
            ->postJson('/api/v1/keys/envelopes', [
                'content_type' => EnvelopeContentType::Sync->value,
                'content_id' => $contentId,
                'sender_device_id' => $sender['id'],
                'envelopes' => [
                    [
                        'recipient_device_id' => $recipient['id'],
                        'encrypted_cek' => base64_encode('fake-cek-ciphertext'),
                        'ephemeral_public_key' => $this->publicKey('ephemeral'),
                        'iv' => base64_encode('123456789012'),
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonCount(1, 'data.envelopes')
            ->assertJsonPath('data.envelopes.0.content_id', $contentId);

        $this->assertDatabaseHas('key_envelopes', [
            'content_id' => $contentId,
            'recipient_device_id' => $recipient['id'],
            'sender_device_id' => $sender['id'],
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/keys/envelopes?'.http_build_query([
                'content_type' => 'sync',
                'content_id' => $contentId,
                'device_id' => $recipient['id'],
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.envelopes');

        $this->withToken($token)
            ->getJson('/api/v1/keys/envelopes/for-me?device_id='.$recipient['id'].'&content_type=sync')
            ->assertOk()
            ->assertJsonCount(1, 'data.envelopes');
    }

    public function test_cannot_store_envelope_for_pending_recipient(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);
        $sender = $this->registerDevice($token, 'sender');
        $pending = $this->registerDevice($token, 'pending-device');

        $this->withToken($token)
            ->postJson('/api/v1/keys/envelopes', [
                'content_type' => 'sync',
                'content_id' => (string) Str::uuid(),
                'sender_device_id' => $sender['id'],
                'envelopes' => [
                    [
                        'recipient_device_id' => $pending['id'],
                        'encrypted_cek' => 'x',
                        'ephemeral_public_key' => 'y',
                        'iv' => 'z',
                    ],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_device_can_rotate_its_own_key(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);
        $device = $this->registerDevice($token, 'device-1');
        $newKey = $this->publicKey('rotated-key-v2');

        $this->withToken($token)
            ->postJson('/api/v1/keys/devices/'.$device['id'].'/rotate', [
                'public_key' => $newKey,
                'actor_device_id' => $device['id'],
            ])
            ->assertOk()
            ->assertJsonPath('data.device.public_key', $newKey);

        $this->assertDatabaseHas('user_devices', [
            'id' => $device['id'],
            'fingerprint' => hash('sha256', $newKey),
        ]);
    }

    public function test_cannot_rotate_another_devices_key(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);
        $first = $this->registerDevice($token, 'device-1');
        $second = $this->registerDevice($token, 'device-2');

        $this->withToken($token)
            ->postJson('/api/v1/devices/'.$second['id'].'/approve', [
                'actor_device_id' => $first['id'],
            ])
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/v1/keys/devices/'.$second['id'].'/rotate', [
                'public_key' => $this->publicKey('hijack'),
                'actor_device_id' => $first['id'],
            ])
            ->assertStatus(422);
    }

    /**
     * @return array<string, mixed>
     */
    private function registerDevice(string $token, string $seed): array
    {
        return $this->withToken($token)
            ->postJson('/api/v1/devices', [
                'name' => $seed,
                'platform' => 'web',
                'public_key' => $this->publicKey($seed),
            ])
            ->json('data.device');
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
