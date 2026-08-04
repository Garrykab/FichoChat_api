<?php

namespace Tests\Feature\Media;

use App\Enums\DeviceStatus;
use App\Enums\MediaStatus;
use App\Enums\UploadSessionStatus;
use App\Jobs\Media\CleanupStaleUploadSessionsJob;
use App\Models\UploadSession;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CleanupStaleUploadSessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_open_sessions_are_marked_and_media_failed(): void
    {
        Storage::fake('media');

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $aliceDevice = $this->registerDevice($alice, 'alice-dev');
        $this->registerDevice($bob, 'bob-dev');

        $conversationId = $this->actingAs($alice, 'api')
            ->postJson('/api/v1/conversations', ['user_id' => $bob->id])
            ->json('data.conversation.id');

        $sessionResponse = $this->actingAs($alice, 'api')
            ->postJson('/api/v1/media/sessions', [
                'conversation_id' => $conversationId,
                'uploader_device_id' => $aliceDevice->id,
                'type' => 'image',
                'mime_type' => 'image/png',
                'size_bytes' => 12,
            ]);

        $sessionId = $sessionResponse->json('data.session.id');
        $mediaId = $sessionResponse->json('data.media.id');

        $this->actingAs($alice, 'api')->call(
            'PUT',
            '/api/v1/media/sessions/'.$sessionId.'/content',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'],
            'encrypted-blob',
        );

        UploadSession::query()->whereKey($sessionId)->update([
            'expires_at' => now()->subMinute(),
        ]);

        (new CleanupStaleUploadSessionsJob)->handle();

        $this->assertDatabaseHas('upload_sessions', [
            'id' => $sessionId,
            'status' => UploadSessionStatus::Expired->value,
        ]);

        $this->assertDatabaseHas('medias', [
            'id' => $mediaId,
            'status' => MediaStatus::Failed->value,
            'storage_path' => null,
        ]);
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
