<?php

namespace Tests\Feature\Users;

use App\Enums\DeviceStatus;
use App\Enums\EnvelopeContentType;
use App\Models\KeyEnvelope;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_get_private_profile(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/users/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', $user->username)
            ->assertJsonPath('data.user.profile.display_name', $user->username);
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);

        $this->withToken($token)
            ->postJson('/api/v1/users/me/profile', [
                'display_name' => 'Alice Doe',
                'bio' => 'Hello FichoChat',
                'locale' => 'fr',
                'theme' => 'dark',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.profile.display_name', 'Alice Doe')
            ->assertJsonPath('data.user.profile.bio', 'Hello FichoChat')
            ->assertJsonPath('data.user.profile.theme', 'dark');
    }

    public function test_user_can_upload_encrypted_avatar(): void
    {
        Storage::fake('media');

        $user = User::factory()->create();
        $device = $this->registerDevice($user, 'alice-device');
        $token = $this->loginToken($user);

        $ciphertext = random_bytes(64);
        $checksum = hash('sha256', $ciphertext);

        $this->withToken($token)
            ->post('/api/v1/users/me/profile', [
                'display_name' => 'With Avatar',
                'avatar' => UploadedFile::fake()->createWithContent('avatar.enc', $ciphertext),
                'avatar_content_iv' => base64_encode(random_bytes(12)),
                'avatar_checksum_sha256' => $checksum,
                'avatar_mime_type' => 'image/jpeg',
                'avatar_size_bytes' => 48,
                'uploader_device_id' => $device->id,
                'envelopes' => [
                    [
                        'recipient_device_id' => $device->id,
                        'encrypted_cek' => base64_encode('cek'),
                        'ephemeral_public_key' => $this->publicKey('eph'),
                        'iv' => base64_encode('iviviviviviv'),
                    ],
                ],
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.profile.display_name', 'With Avatar')
            ->assertJsonPath('data.user.profile.avatar.mime_type', 'image/jpeg')
            ->assertJsonPath('data.user.profile.avatar_url', null);

        $user->refresh()->load('profile');
        $this->assertNotNull($user->profile?->avatar_path);
        $this->assertSame('media', $user->profile?->avatar_disk);
        Storage::disk('media')->assertExists($user->profile->avatar_path);

        $this->assertDatabaseHas('key_envelopes', [
            'content_type' => EnvelopeContentType::Avatar->value,
            'content_id' => $user->id,
            'recipient_device_id' => $device->id,
        ]);

        $this->withToken($token)
            ->get('/api/v1/users/'.$user->id.'/avatar')
            ->assertOk();
    }

    public function test_plaintext_image_avatar_is_rejected_without_crypto_meta(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);

        $this->withToken($token)
            ->post('/api/v1/users/me/profile', [
                'avatar' => UploadedFile::fake()->image('avatar.jpg'),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertStatus(422);
    }

    public function test_user_can_change_password(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);

        $this->withToken($token)
            ->postJson('/api/v1/users/me/password', [
                'current_password' => 'password',
                'password' => 'NewPassword1!',
                'password_confirmation' => 'NewPassword1!',
                'revoke_other_sessions' => true,
            ])
            ->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'NewPassword1!',
        ])->assertOk();
    }

    public function test_user_can_search_active_users(): void
    {
        $me = User::factory()->create(['username' => 'searcher']);
        $match = User::factory()->create(['username' => 'alice42']);
        User::factory()->create(['username' => 'bob']);
        User::factory()->unverified()->create(['username' => 'alicepending']);

        $token = $this->loginToken($me);

        $this->withToken($token)
            ->getJson('/api/v1/users/search?q=ali')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.users')
            ->assertJsonPath('data.users.0.username', $match->username);
    }

    public function test_user_search_is_tolerant_to_near_matches(): void
    {
        $me = User::factory()->create(['username' => 'searcher']);
        $doubleR = User::factory()->create(['username' => 'garrykab']);
        User::factory()->create(['username' => 'zzzother']);

        $token = $this->loginToken($me);

        $this->withToken($token)
            ->getJson('/api/v1/users/search?q=gary')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.users')
            ->assertJsonPath('data.users.0.username', $doubleR->username);
    }

    public function test_user_can_view_public_profile(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create(['username' => 'publicuser']);
        $other->profile->update([
            'display_name' => 'Public User',
            'bio' => 'Visible bio',
        ]);

        $token = $this->loginToken($me);

        $this->withToken($token)
            ->getJson('/api/v1/users/'.$other->id)
            ->assertOk()
            ->assertJsonPath('data.user.username', 'publicuser')
            ->assertJsonPath('data.user.display_name', 'Public User')
            ->assertJsonMissingPath('data.user.email');
    }

    public function test_user_can_complete_profile_setup(): void
    {
        Storage::fake('media');

        $user = User::factory()->create([
            'username' => 'tmp_abcdefghijkl',
            'profile_setup_completed_at' => null,
        ]);
        $device = $this->registerDevice($user, 'setup-device');
        $token = $this->loginToken($user);

        $ciphertext = random_bytes(32);
        $checksum = hash('sha256', $ciphertext);

        $this->withToken($token)
            ->post('/api/v1/users/me/setup-profile', [
                'username' => 'alice',
                'display_name' => 'Alice',
                'avatar' => UploadedFile::fake()->createWithContent('avatar.enc', $ciphertext),
                'avatar_content_iv' => base64_encode(random_bytes(12)),
                'avatar_checksum_sha256' => $checksum,
                'avatar_mime_type' => 'image/png',
                'avatar_size_bytes' => 20,
                'uploader_device_id' => $device->id,
                'envelopes' => [
                    [
                        'recipient_device_id' => $device->id,
                        'encrypted_cek' => base64_encode('cek'),
                        'ephemeral_public_key' => $this->publicKey('eph-setup'),
                        'iv' => base64_encode('iviviviviviv'),
                    ],
                ],
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.username', 'alice')
            ->assertJsonPath('data.user.profile_setup_completed', true)
            ->assertJsonPath('data.user.profile.display_name', 'Alice')
            ->assertJsonPath('data.user.profile.avatar.mime_type', 'image/png');

        $this->assertNotNull($user->fresh()->profile_setup_completed_at);
        $this->assertTrue(KeyEnvelope::query()->where('content_type', EnvelopeContentType::Avatar->value)->exists());
    }

    public function test_profile_setup_rejects_duplicate_username(): void
    {
        User::factory()->create(['username' => 'taken']);
        $user = User::factory()->create([
            'username' => 'tmp_pendinguser',
            'profile_setup_completed_at' => null,
        ]);
        $token = $this->loginToken($user);

        $this->withToken($token)
            ->postJson('/api/v1/users/me/setup-profile', [
                'username' => 'taken',
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
