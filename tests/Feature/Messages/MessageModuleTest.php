<?php

namespace Tests\Feature\Messages;

use App\Enums\DeviceStatus;
use App\Enums\ReceiptStatus;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessageModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_send_and_list_encrypted_message(): void
    {
        [$alice, $bob, $aliceDevice, $bobDevice, $conversationId] = $this->setupPrivateChat();

        $response = $this->actingAs($alice, 'api')
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
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message.ciphertext', base64_encode('cipher'));

        $messageId = $response->json('data.message.id');

        $this->assertDatabaseHas('key_envelopes', [
            'content_type' => 'message',
            'content_id' => $messageId,
            'recipient_device_id' => $bobDevice->id,
        ]);

        $this->actingAs($bob, 'api')
            ->getJson('/api/v1/conversations/'.$conversationId.'/messages')
            ->assertOk()
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonPath('data.messages.0.id', $messageId);
    }

    public function test_recipient_can_mark_delivered_and_read(): void
    {
        [$alice, $bob, $aliceDevice, $bobDevice, $conversationId] = $this->setupPrivateChat();

        $messageId = $this->actingAs($alice, 'api')
            ->postJson('/api/v1/conversations/'.$conversationId.'/messages', $this->messagePayload($aliceDevice, $bobDevice))
            ->json('data.message.id');

        $this->flushAuthState();

        $this->actingAs($bob, 'api')
            ->postJson('/api/v1/messages/'.$messageId.'/receipts', [
                'status' => ReceiptStatus::Delivered->value,
                'device_id' => $bobDevice->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.receipt.status', ReceiptStatus::Delivered->value);

        $this->actingAs($bob, 'api')
            ->postJson('/api/v1/messages/'.$messageId.'/receipts', [
                'status' => ReceiptStatus::Read->value,
                'device_id' => $bobDevice->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.receipt.status', ReceiptStatus::Read->value);
    }

    public function test_sender_can_edit_and_delete_message(): void
    {
        [$alice, $bob, $aliceDevice, $bobDevice, $conversationId] = $this->setupPrivateChat();

        $messageId = $this->actingAs($alice, 'api')
            ->postJson('/api/v1/conversations/'.$conversationId.'/messages', $this->messagePayload($aliceDevice, $bobDevice))
            ->json('data.message.id');

        $edit = $this->actingAs($alice, 'api')
            ->patchJson('/api/v1/messages/'.$messageId, [
                'ciphertext' => base64_encode('edited'),
                'iv' => base64_encode('new-iv-123456'),
            ]);

        $edit->assertOk()
            ->assertJsonPath('data.message.ciphertext', base64_encode('edited'));

        $this->assertNotNull($edit->json('data.message.edited_at'));

        $this->actingAs($alice, 'api')
            ->deleteJson('/api/v1/messages/'.$messageId)
            ->assertOk()
            ->assertJsonPath('data.message.deleted_for_everyone', true)
            ->assertJsonPath('data.message.ciphertext', null);
    }

    public function test_participant_can_delete_message_for_self_only(): void
    {
        [$alice, $bob, $aliceDevice, $bobDevice, $conversationId] = $this->setupPrivateChat();

        $messageId = $this->actingAs($alice, 'api')
            ->postJson('/api/v1/conversations/'.$conversationId.'/messages', $this->messagePayload($aliceDevice, $bobDevice))
            ->json('data.message.id');

        $this->flushAuthState();

        $this->actingAs($bob, 'api')
            ->deleteJson('/api/v1/messages/'.$messageId.'/for-me')
            ->assertOk()
            ->assertJsonPath('data.deleted_for_me', true);

        $this->actingAs($bob, 'api')
            ->getJson('/api/v1/conversations/'.$conversationId.'/messages')
            ->assertOk()
            ->assertJsonCount(0, 'data.messages');

        $this->flushAuthState();

        $this->actingAs($alice, 'api')
            ->getJson('/api/v1/conversations/'.$conversationId.'/messages')
            ->assertOk()
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonPath('data.messages.0.id', $messageId);
    }

    public function test_non_participant_cannot_send_message(): void
    {
        [$alice, $bob, $aliceDevice, $bobDevice, $conversationId] = $this->setupPrivateChat();
        $charlie = User::factory()->create();
        $charlieDevice = $this->registerDevice($charlie, 'charlie');

        $this->flushAuthState();

        $this->actingAs($charlie, 'api')
            ->postJson('/api/v1/conversations/'.$conversationId.'/messages', $this->messagePayload($charlieDevice, $bobDevice))
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: User, 2: UserDevice, 3: UserDevice, 4: string}
     */
    private function setupPrivateChat(): array
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $aliceDevice = $this->registerDevice($alice, 'alice-dev');
        $bobDevice = $this->registerDevice($bob, 'bob-dev');

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

    /**
     * @return array<string, mixed>
     */
    private function messagePayload(UserDevice $sender, UserDevice $recipient): array
    {
        return [
            'sender_device_id' => $sender->id,
            'ciphertext' => base64_encode('cipher-'.Str::random(8)),
            'iv' => base64_encode('123456789012'),
            'envelopes' => [
                [
                    'recipient_device_id' => $recipient->id,
                    'encrypted_cek' => base64_encode('cek'),
                    'ephemeral_public_key' => $this->publicKey('eph-'.Str::random(4)),
                    'iv' => base64_encode('iviviviviviv'),
                ],
            ],
        ];
    }

    private function publicKey(string $seed): string
    {
        return "-----BEGIN PUBLIC KEY-----\n".base64_encode(str_pad($seed, 64, 'x'))."\n-----END PUBLIC KEY-----";
    }
}
