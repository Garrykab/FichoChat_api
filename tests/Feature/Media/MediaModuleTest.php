<?php

namespace Tests\Feature\Media;

use App\Enums\DeviceStatus;
use App\Enums\MediaStatus;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_upload_complete_attach_and_download_media(): void
    {
        Storage::fake('media');

        [$alice, $bob, $aliceDevice, $bobDevice, $conversationId] = $this->setupPrivateChat();

        $sessionResponse = $this->actingAs($alice, 'api')
            ->postJson('/api/v1/media/sessions', [
                'conversation_id' => $conversationId,
                'uploader_device_id' => $aliceDevice->id,
                'type' => 'image',
                'mime_type' => 'image/png',
                'original_filename' => 'photo.png',
                'size_bytes' => 12,
            ]);

        $sessionResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.media.status', MediaStatus::Pending->value)
            ->assertJsonPath('data.session.status', 'open');

        $sessionId = $sessionResponse->json('data.session.id');
        $mediaId = $sessionResponse->json('data.media.id');
        $blob = 'encrypted-blob';

        $this->actingAs($alice, 'api')
            ->call(
                'PUT',
                '/api/v1/media/sessions/'.$sessionId.'/content',
                [],
                [],
                [],
                [
                    'CONTENT_TYPE' => 'application/octet-stream',
                    'HTTP_ACCEPT' => 'application/json',
                ],
                $blob,
            )
            ->assertOk()
            ->assertJsonPath('data.media.status', MediaStatus::Uploading->value)
            ->assertJsonPath('data.session.bytes_received', strlen($blob));

        Storage::disk('media')->assertExists('conversations/'.$conversationId.'/'.$mediaId.'.enc');

        $checksum = hash('sha256', $blob);

        $this->actingAs($alice, 'api')
            ->postJson('/api/v1/media/sessions/'.$sessionId.'/complete', [
                'content_iv' => base64_encode('123456789012'),
                'checksum_sha256' => $checksum,
                'uploader_device_id' => $aliceDevice->id,
                'envelopes' => [
                    [
                        'recipient_device_id' => $bobDevice->id,
                        'encrypted_cek' => base64_encode('cek-media'),
                        'ephemeral_public_key' => $this->publicKey('eph-media'),
                        'iv' => base64_encode('iviviviviviv'),
                    ],
                    [
                        'recipient_device_id' => $aliceDevice->id,
                        'encrypted_cek' => base64_encode('cek-media-self'),
                        'ephemeral_public_key' => $this->publicKey('eph-media-2'),
                        'iv' => base64_encode('iviviviviviv'),
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.media.status', MediaStatus::Ready->value)
            ->assertJsonPath('data.media.checksum_sha256', $checksum);

        $this->assertDatabaseHas('key_envelopes', [
            'content_type' => 'media',
            'content_id' => $mediaId,
            'recipient_device_id' => $bobDevice->id,
        ]);

        $messageId = $this->actingAs($alice, 'api')
            ->postJson('/api/v1/conversations/'.$conversationId.'/messages', [
                'sender_device_id' => $aliceDevice->id,
                'ciphertext' => base64_encode('caption'),
                'iv' => base64_encode('123456789012'),
                'type' => 'image',
                'media_ids' => [$mediaId],
                'envelopes' => [
                    [
                        'recipient_device_id' => $bobDevice->id,
                        'encrypted_cek' => base64_encode('cek'),
                        'ephemeral_public_key' => $this->publicKey('eph-msg'),
                        'iv' => base64_encode('iviviviviviv'),
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.message.type', 'image')
            ->assertJsonPath('data.message.medias.0.id', $mediaId)
            ->json('data.message.id');

        $this->assertDatabaseHas('medias', [
            'id' => $mediaId,
            'message_id' => $messageId,
            'status' => MediaStatus::Ready->value,
        ]);

        $this->flushAuthState();

        $download = $this->actingAs($bob, 'api')
            ->get('/api/v1/media/'.$mediaId.'/download');

        $download->assertOk();
        $this->assertSame($blob, $download->streamedContent());

        $this->actingAs($bob, 'api')
            ->getJson('/api/v1/media/'.$mediaId)
            ->assertOk()
            ->assertJsonPath('data.media.id', $mediaId)
            ->assertJsonPath('data.media.status', MediaStatus::Ready->value);
    }

    public function test_non_participant_cannot_create_media_session(): void
    {
        [$alice, $bob, $aliceDevice, $bobDevice, $conversationId] = $this->setupPrivateChat();
        $charlie = User::factory()->create();
        $charlieDevice = $this->registerDevice($charlie, 'charlie');

        $this->flushAuthState();

        $this->actingAs($charlie, 'api')
            ->postJson('/api/v1/media/sessions', [
                'conversation_id' => $conversationId,
                'uploader_device_id' => $charlieDevice->id,
                'type' => 'document',
                'mime_type' => 'application/pdf',
                'size_bytes' => 100,
            ])
            ->assertStatus(422);
    }

    public function test_cannot_attach_media_twice(): void
    {
        Storage::fake('media');

        [$alice, $bob, $aliceDevice, $bobDevice, $conversationId] = $this->setupPrivateChat();
        $mediaId = $this->uploadReadyMedia($alice, $aliceDevice, $bobDevice, $conversationId);

        $payload = [
            'sender_device_id' => $aliceDevice->id,
            'ciphertext' => base64_encode('caption'),
            'iv' => base64_encode('123456789012'),
            'type' => 'image',
            'media_ids' => [$mediaId],
            'envelopes' => [
                [
                    'recipient_device_id' => $bobDevice->id,
                    'encrypted_cek' => base64_encode('cek'),
                    'ephemeral_public_key' => $this->publicKey('eph'),
                    'iv' => base64_encode('iviviviviviv'),
                ],
            ],
        ];

        $this->actingAs($alice, 'api')
            ->postJson('/api/v1/conversations/'.$conversationId.'/messages', $payload)
            ->assertCreated();

        $this->actingAs($alice, 'api')
            ->postJson('/api/v1/conversations/'.$conversationId.'/messages', $payload)
            ->assertStatus(422);
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

    private function uploadReadyMedia(
        User $uploader,
        UserDevice $uploaderDevice,
        UserDevice $peerDevice,
        string $conversationId,
    ): string {
        $sessionResponse = $this->actingAs($uploader, 'api')
            ->postJson('/api/v1/media/sessions', [
                'conversation_id' => $conversationId,
                'uploader_device_id' => $uploaderDevice->id,
                'type' => 'image',
                'mime_type' => 'image/png',
                'size_bytes' => 8,
            ]);

        $sessionId = $sessionResponse->json('data.session.id');
        $mediaId = $sessionResponse->json('data.media.id');
        $blob = 'enc-data';

        $this->actingAs($uploader, 'api')->call(
            'PUT',
            '/api/v1/media/sessions/'.$sessionId.'/content',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'],
            $blob,
        );

        $this->actingAs($uploader, 'api')
            ->postJson('/api/v1/media/sessions/'.$sessionId.'/complete', [
                'content_iv' => base64_encode('123456789012'),
                'checksum_sha256' => hash('sha256', $blob),
                'uploader_device_id' => $uploaderDevice->id,
                'envelopes' => [
                    [
                        'recipient_device_id' => $peerDevice->id,
                        'encrypted_cek' => base64_encode('cek'),
                        'ephemeral_public_key' => $this->publicKey('eph-m'),
                        'iv' => base64_encode('iviviviviviv'),
                    ],
                ],
            ]);

        return $mediaId;
    }

    public function test_participant_can_upload_media_in_chunks(): void
    {
        Storage::fake('media');

        [$alice, $bob, $aliceDevice, $bobDevice, $conversationId] = $this->setupPrivateChat();

        $sessionResponse = $this->actingAs($alice, 'api')
            ->postJson('/api/v1/media/sessions', [
                'conversation_id' => $conversationId,
                'uploader_device_id' => $aliceDevice->id,
                'type' => 'document',
                'mime_type' => 'application/pdf',
                'original_filename' => 'doc.pdf',
                'size_bytes' => 12,
            ])
            ->assertCreated();

        $sessionId = $sessionResponse->json('data.session.id');
        $mediaId = $sessionResponse->json('data.media.id');
        $blob = 'ABCDEFGHIJKL'; // 12 bytes

        $this->actingAs($alice, 'api')
            ->call(
                'PUT',
                '/api/v1/media/sessions/'.$sessionId.'/content',
                [],
                [],
                [],
                [
                    'CONTENT_TYPE' => 'application/octet-stream',
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_CONTENT_RANGE' => 'bytes 0-5/12',
                ],
                substr($blob, 0, 6),
            )
            ->assertOk()
            ->assertJsonPath('data.session.bytes_received', 6);

        $this->actingAs($alice, 'api')
            ->call(
                'PUT',
                '/api/v1/media/sessions/'.$sessionId.'/content',
                [],
                [],
                [],
                [
                    'CONTENT_TYPE' => 'application/octet-stream',
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_CONTENT_RANGE' => 'bytes 6-11/12',
                ],
                substr($blob, 6, 6),
            )
            ->assertOk()
            ->assertJsonPath('data.session.bytes_received', 12);

        $this->assertSame($blob, Storage::disk('media')->get('conversations/'.$conversationId.'/'.$mediaId.'.enc'));
    }

    private function publicKey(string $seed): string
    {
        return "-----BEGIN PUBLIC KEY-----\n".base64_encode(str_pad($seed, 64, 'x'))."\n-----END PUBLIC KEY-----";
    }
}
