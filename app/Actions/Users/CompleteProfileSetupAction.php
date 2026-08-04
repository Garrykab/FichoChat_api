<?php

namespace App\Actions\Users;

use App\Enums\ActivityLogType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CompleteProfileSetupAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly EnsureUserProfileAction $ensureUserProfileAction,
        private readonly UploadEncryptedAvatarAction $uploadEncryptedAvatarAction,
    ) {}

    /**
     * @param  array{
     *     username: string,
     *     display_name?: string|null,
     *     avatar_content_iv?: string,
     *     avatar_checksum_sha256?: string,
     *     avatar_mime_type?: string,
     *     avatar_size_bytes?: int,
     *     uploader_device_id?: string,
     *     envelopes?: list<array<string, mixed>>
     * }  $data
     */
    public function execute(User $user, array $data, ?UploadedFile $avatar = null): User
    {
        $user = DB::transaction(function () use ($user, $data, $avatar) {
            $user->forceFill([
                'username' => $data['username'],
                'profile_setup_completed_at' => now(),
            ])->save();

            $profile = $this->ensureUserProfileAction->execute($user);

            $profile->fill([
                'display_name' => $data['display_name'] ?? $data['username'],
            ])->save();

            if ($avatar !== null) {
                $this->uploadEncryptedAvatarAction->execute($user, $profile, $avatar, [
                    'avatar_content_iv' => $data['avatar_content_iv'],
                    'avatar_checksum_sha256' => $data['avatar_checksum_sha256'],
                    'avatar_mime_type' => $data['avatar_mime_type'],
                    'avatar_size_bytes' => (int) $data['avatar_size_bytes'],
                    'uploader_device_id' => $data['uploader_device_id'],
                    'envelopes' => $data['envelopes'],
                ]);
            }

            return $user->fresh(['profile']);
        });

        $this->auditLogger->activity(
            ActivityLogType::ProfileUpdated,
            $user,
            ['username' => $user->username, 'setup' => true, 'avatar_updated' => $avatar !== null],
            User::class,
            $user->id,
        );

        return $user;
    }
}
