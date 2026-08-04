<?php

namespace App\Actions\Users;

use App\Enums\ActivityLogType;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UpdateProfileAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly UploadEncryptedAvatarAction $uploadEncryptedAvatarAction,
    ) {}

    /**
     * @param  array{
     *     display_name?: string|null,
     *     bio?: string|null,
     *     locale?: string,
     *     theme?: string,
     *     avatar_content_iv?: string,
     *     avatar_checksum_sha256?: string,
     *     avatar_mime_type?: string,
     *     avatar_size_bytes?: int,
     *     uploader_device_id?: string,
     *     envelopes?: list<array<string, mixed>>
     * }  $data
     */
    public function execute(User $user, array $data, ?UploadedFile $avatar = null): UserProfile
    {
        $avatarMeta = null;
        if ($avatar !== null) {
            $avatarMeta = [
                'avatar_content_iv' => $data['avatar_content_iv'],
                'avatar_checksum_sha256' => $data['avatar_checksum_sha256'],
                'avatar_mime_type' => $data['avatar_mime_type'],
                'avatar_size_bytes' => (int) $data['avatar_size_bytes'],
                'uploader_device_id' => $data['uploader_device_id'],
                'envelopes' => $data['envelopes'],
            ];
        }

        $profileFields = collect($data)->only(['display_name', 'bio', 'locale', 'theme'])->all();

        $profile = DB::transaction(function () use ($user, $profileFields, $avatar, $avatarMeta) {
            /** @var UserProfile $profile */
            $profile = $user->profile()->firstOrCreate(
                ['user_id' => $user->id],
                ['display_name' => $user->username],
            );

            if ($profileFields !== []) {
                $profile->fill($profileFields)->save();
            }

            if ($avatar !== null && $avatarMeta !== null) {
                $profile = $this->uploadEncryptedAvatarAction->execute($user, $profile, $avatar, $avatarMeta);
            }

            return $profile->fresh();
        });

        $this->auditLogger->activity(
            ActivityLogType::ProfileUpdated,
            $user,
            ['avatar_updated' => $avatar !== null],
            UserProfile::class,
            $profile->id,
        );

        return $profile;
    }
}
