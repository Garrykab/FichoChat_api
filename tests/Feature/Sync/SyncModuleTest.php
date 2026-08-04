<?php

namespace Tests\Feature\Sync;

use App\Enums\DeviceStatus;
use App\Enums\SyncStatus;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_approved_device_gets_sync_state_and_can_bootstrap(): void
    {
        $alice = User::factory()->create();
        $device = $this->registerDevice($alice, 'alice-primary');

        $this->assertDatabaseHas('device_sync_states', [
            'device_id' => $device->id,
            'user_id' => $alice->id,
            'status' => SyncStatus::PendingBootstrap->value,
        ]);

        $this->actingAs($alice, 'api')
            ->getJson('/api/v1/sync/state?device_id='.$device->id)
            ->assertOk()
            ->assertJsonPath('data.state.needs_bootstrap', true)
            ->assertJsonPath('data.state.device_id', $device->id);

        $bootstrap = $this->actingAs($alice, 'api')
            ->getJson('/api/v1/sync/bootstrap?device_id='.$device->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertArrayHasKey('conversations', $bootstrap->json('data'));
        $this->assertArrayHasKey('messages_by_conversation', $bootstrap->json('data'));
        $this->assertArrayHasKey('envelopes', $bootstrap->json('data'));

        $this->actingAs($alice, 'api')
            ->postJson('/api/v1/sync/ack', [
                'device_id' => $device->id,
                'cursor_at' => now()->toISOString(),
                'bootstrap_completed' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.state.status', SyncStatus::Ready->value)
            ->assertJsonPath('data.state.needs_bootstrap', false);
    }

    public function test_delta_returns_new_messages_after_cursor(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $aliceDevice = $this->registerDevice($alice, 'alice');
        $bobDevice = $this->registerDevice($bob, 'bob');

        $conversationId = $this->actingAs($alice, 'api')
            ->postJson('/api/v1/conversations', ['user_id' => $bob->id])
            ->json('data.conversation.id');

        $since = now()->subMinute()->toISOString();

        $this->actingAs($alice, 'api')
            ->postJson('/api/v1/conversations/'.$conversationId.'/messages', [
                'sender_device_id' => $aliceDevice->id,
                'ciphertext' => base64_encode('cipher'),
                'iv' => base64_encode('123456789012'),
                'envelopes' => [
                    [
                        'recipient_device_id' => $bobDevice->id,
                        'encrypted_cek' => base64_encode('cek'),
                        'ephemeral_public_key' => $this->publicKey('eph'),
                        'iv' => base64_encode('iviviviviviv'),
                    ],
                    [
                        'recipient_device_id' => $aliceDevice->id,
                        'encrypted_cek' => base64_encode('cek-self'),
                        'ephemeral_public_key' => $this->publicKey('eph2'),
                        'iv' => base64_encode('iviviviviviv'),
                    ],
                ],
            ])
            ->assertCreated();

        $this->actingAs($alice, 'api')
            ->getJson('/api/v1/sync/delta?device_id='.$aliceDevice->id.'&since='.urlencode($since))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.messages');
    }

    public function test_approving_device_creates_sync_state_and_lists_missing_envelopes(): void
    {
        $alice = User::factory()->create();
        $primary = $this->registerDevice($alice, 'primary');
        $pending = UserDevice::query()->create([
            'user_id' => $alice->id,
            'name' => 'secondary',
            'platform' => 'web',
            'public_key' => $this->publicKey('secondary'),
            'fingerprint' => hash('sha256', $this->publicKey('secondary')),
            'status' => DeviceStatus::Pending,
        ]);

        $bob = User::factory()->create();
        $bobDevice = $this->registerDevice($bob, 'bob');

        $conversationId = $this->actingAs($alice, 'api')
            ->postJson('/api/v1/conversations', ['user_id' => $bob->id])
            ->json('data.conversation.id');

        $messageId = $this->actingAs($alice, 'api')
            ->postJson('/api/v1/conversations/'.$conversationId.'/messages', [
                'sender_device_id' => $primary->id,
                'ciphertext' => base64_encode('cipher'),
                'iv' => base64_encode('123456789012'),
                'envelopes' => [
                    [
                        'recipient_device_id' => $bobDevice->id,
                        'encrypted_cek' => base64_encode('cek'),
                        'ephemeral_public_key' => $this->publicKey('eph'),
                        'iv' => base64_encode('iviviviviviv'),
                    ],
                    [
                        'recipient_device_id' => $primary->id,
                        'encrypted_cek' => base64_encode('cek-self'),
                        'ephemeral_public_key' => $this->publicKey('eph2'),
                        'iv' => base64_encode('iviviviviviv'),
                    ],
                ],
            ])
            ->json('data.message.id');

        $this->actingAs($alice, 'api')
            ->postJson('/api/v1/devices/'.$pending->id.'/approve', [
                'actor_device_id' => $primary->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('device_sync_states', [
            'device_id' => $pending->id,
            'status' => SyncStatus::PendingBootstrap->value,
        ]);

        $this->actingAs($alice, 'api')
            ->getJson('/api/v1/sync/devices/'.$pending->id.'/missing-envelopes?actor_device_id='.$primary->id)
            ->assertOk()
            ->assertJsonPath('data.target_device_id', $pending->id)
            ->assertJsonPath('data.messages.0.content_id', $messageId);
    }

    private function registerDevice(User $user, string $seed): UserDevice
    {
        $device = UserDevice::query()->create([
            'user_id' => $user->id,
            'name' => $seed,
            'platform' => 'web',
            'public_key' => $this->publicKey($seed),
            'fingerprint' => hash('sha256', $this->publicKey($seed)),
            'status' => DeviceStatus::Approved,
            'approved_at' => now(),
        ]);

        // Mimic RegisterDeviceAction sync state creation for factory-created devices
        \App\Models\DeviceSyncState::query()->firstOrCreate(
            ['device_id' => $device->id],
            [
                'user_id' => $user->id,
                'status' => SyncStatus::PendingBootstrap,
            ],
        );

        return $device;
    }

    private function publicKey(string $seed): string
    {
        return "-----BEGIN PUBLIC KEY-----\n".base64_encode(str_pad($seed, 64, 'x'))."\n-----END PUBLIC KEY-----";
    }
}
