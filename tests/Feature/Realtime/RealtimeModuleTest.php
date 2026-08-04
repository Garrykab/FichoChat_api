<?php

namespace Tests\Feature\Realtime;

use App\Enums\DeviceStatus;
use App\Events\DeviceStatusChanged;
use App\Events\MessageReceiptUpdated;
use App\Events\MessageSent;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RealtimeModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_realtime_events_are_queued_not_sync_broadcast(): void
    {
        $this->assertTrue(
            is_subclass_of(MessageSent::class, \Illuminate\Contracts\Broadcasting\ShouldBroadcast::class),
        );
        $this->assertFalse(
            is_subclass_of(MessageSent::class, \Illuminate\Contracts\Broadcasting\ShouldBroadcastNow::class),
        );
        $this->assertSame('broadcasts', (new MessageSent(new \App\Models\Message))->broadcastQueue());
    }

    public function test_sending_message_dispatches_message_sent_event(): void
    {
        Event::fake([MessageSent::class]);

        [$alice, $bob, $aliceDevice, $bobDevice, $conversationId] = $this->setupPrivateChat();

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
                ],
            ])
            ->assertCreated();

        Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($conversationId) {
            return $event->message->conversation_id === $conversationId
                && $event->broadcastAs() === 'message.sent';
        });
    }

    public function test_receipt_dispatches_realtime_event(): void
    {
        Event::fake([MessageSent::class, MessageReceiptUpdated::class]);

        [$alice, $bob, $aliceDevice, $bobDevice, $conversationId] = $this->setupPrivateChat();

        $messageId = $this->actingAs($alice, 'api')
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
                ],
            ])
            ->json('data.message.id');

        $this->flushAuthState();

        $this->actingAs($bob, 'api')
            ->postJson('/api/v1/messages/'.$messageId.'/receipts', [
                'status' => 'read',
                'device_id' => $bobDevice->id,
            ])
            ->assertOk();

        Event::assertDispatched(MessageReceiptUpdated::class);
    }

    public function test_realtime_config_endpoint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->getJson('/api/v1/realtime/config')
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.driver', 'null')
            ->assertJsonStructure(['data' => ['auth_endpoint', 'force_tls']]);
    }

    public function test_participant_can_authorize_conversation_channel(): void
    {
        [$alice, $bob, $aliceDevice, $bobDevice, $conversationId] = $this->setupPrivateChat();

        $socketId = '1234.5678';
        $channelName = 'private-conversation.'.$conversationId;

        $this->actingAs($alice, 'api')
            ->postJson('/api/v1/broadcasting/auth', [
                'socket_id' => $socketId,
                'channel_name' => $channelName,
            ])
            ->assertOk();
    }

    public function test_non_participant_cannot_authorize_conversation_channel(): void
    {
        [$alice, $bob, $aliceDevice, $bobDevice, $conversationId] = $this->setupPrivateChat();
        $charlie = User::factory()->create();

        $this->flushAuthState();

        $this->actingAs($charlie, 'api')
            ->postJson('/api/v1/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-conversation.'.$conversationId,
            ])
            ->assertForbidden();
    }

    public function test_pending_device_dispatches_device_event(): void
    {
        Event::fake([DeviceStatusChanged::class]);

        $alice = User::factory()->create();
        $this->registerDevice($alice, 'primary');

        UserDevice::query()->create([
            'user_id' => $alice->id,
            'name' => 'second',
            'platform' => 'web',
            'public_key' => $this->publicKey('second'),
            'fingerprint' => hash('sha256', $this->publicKey('second')),
            'status' => DeviceStatus::Pending,
        ]);

        // Direct register via action path
        $this->actingAs($alice, 'api')
            ->postJson('/api/v1/devices', [
                'name' => 'phone',
                'platform' => 'web',
                'public_key' => $this->publicKey('phone-new'),
            ])
            ->assertCreated();

        Event::assertDispatched(DeviceStatusChanged::class, function (DeviceStatusChanged $event) {
            return $event->action === 'pending';
        });
    }

    /**
     * @return array{0: User, 1: User, 2: UserDevice, 3: UserDevice, 4: string}
     */
    private function setupPrivateChat(): array
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $aliceDevice = $this->registerDevice($alice, 'alice');
        $bobDevice = $this->registerDevice($bob, 'bob');

        $conversationId = $this->actingAs($alice, 'api')
            ->postJson('/api/v1/conversations', ['user_id' => $bob->id])
            ->json('data.conversation.id');

        return [$alice, $bob, $aliceDevice, $bobDevice, $conversationId];
    }

    private function registerDevice(User $user, string $seed): UserDevice
    {
        return UserDevice::query()->create([
            'user_id' => $user->id,
            'name' => $seed,
            'platform' => 'web',
            'public_key' => $this->publicKey($seed),
            'fingerprint' => hash('sha256', $this->publicKey($seed)),
            'status' => DeviceStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    private function publicKey(string $seed): string
    {
        return "-----BEGIN PUBLIC KEY-----\n".base64_encode(str_pad($seed, 64, 'x'))."\n-----END PUBLIC KEY-----";
    }
}
