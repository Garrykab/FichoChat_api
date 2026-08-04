<?php

namespace App\Actions\Users;

use App\Actions\Keys\StoreKeyEnvelopesAction;
use App\Enums\EnvelopeContentType;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UploadEncryptedAvatarAction
{
    public function __construct(
        private readonly StoreKeyEnvelopesAction $storeKeyEnvelopesAction,
    ) {}

    /**
     * @param  array{
     *     avatar_content_iv: string,
     *     avatar_checksum_sha256: string,
     *     avatar_mime_type: string,
     *     avatar_size_bytes: int,
     *     uploader_device_id: string,
     *     envelopes: list<array<string, mixed>>
     * }  $meta
     */
    public function execute(User $user, UserProfile $profile, UploadedFile $avatar, array $meta): UserProfile
    {
        $ciphertext = $avatar->getContent();
        $checksum = hash('sha256', $ciphertext);

        if (! hash_equals(strtolower($meta['avatar_checksum_sha256']), $checksum)) {
            throw ValidationException::withMessages([
                'avatar_checksum_sha256' => ['Checksum does not match uploaded ciphertext.'],
            ]);
        }

        $disk = 'media';
        $path = 'avatars/'.$user->id.'/avatar.enc';

        if ($profile->avatar_path) {
            Storage::disk($profile->avatar_disk ?: 'public')->delete($profile->avatar_path);
        }

        Storage::disk($disk)->put($path, $ciphertext);

        $profile->fill([
            'avatar_path' => $path,
            'avatar_disk' => $disk,
            'avatar_content_iv' => $meta['avatar_content_iv'],
            'avatar_mime_type' => $meta['avatar_mime_type'],
            'avatar_checksum_sha256' => $checksum,
            'avatar_size_bytes' => $meta['avatar_size_bytes'],
            'avatar_encrypted_size_bytes' => strlen($ciphertext),
        ])->save();

        // content_id = user.id (stable) pour retrouver l’enveloppe avatar.
        $this->storeKeyEnvelopesAction->execute($user, [
            'content_type' => EnvelopeContentType::Avatar->value,
            'content_id' => $user->id,
            'sender_device_id' => $meta['uploader_device_id'],
            'envelopes' => $meta['envelopes'],
        ]);

        return $profile->fresh();
    }
}
