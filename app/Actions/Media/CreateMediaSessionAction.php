<?php

namespace App\Actions\Media;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Enums\UploadSessionStatus;
use App\Models\Conversation;
use App\Models\Media;
use App\Models\UploadSession;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Settings\AppSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateMediaSessionAction
{
    public function __construct(
        private readonly AppSettings $appSettings,
    ) {}

    /**
     * @param  array{
     *     conversation_id: string,
     *     uploader_device_id: string,
     *     type: string,
     *     mime_type: string,
     *     original_filename?: string|null,
     *     size_bytes: int
     * }  $data
     * @return array{media: Media, session: UploadSession}
     */
    public function execute(User $user, array $data): array
    {
        $conversation = Conversation::query()->find($data['conversation_id']);

        if ($conversation === null || ! $conversation->participants()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'conversation_id' => ['Conversation not found or inaccessible.'],
            ]);
        }

        $device = UserDevice::query()
            ->where('id', $data['uploader_device_id'])
            ->where('user_id', $user->id)
            ->first();

        if ($device === null || ! $device->isApproved()) {
            throw ValidationException::withMessages([
                'uploader_device_id' => ['Uploader device must be approved.'],
            ]);
        }

        $maxFileBytes = $this->appSettings->mediaLimits()['max_file_bytes'];
        if ($data['size_bytes'] > $maxFileBytes) {
            throw ValidationException::withMessages([
                'size_bytes' => ['Media exceeds the configured size limit.'],
            ]);
        }

        return DB::transaction(function () use ($user, $data, $conversation, $device) {
            $media = Media::query()->create([
                'conversation_id' => $conversation->id,
                'uploader_user_id' => $user->id,
                'uploader_device_id' => $device->id,
                'type' => MediaType::from($data['type']),
                'mime_type' => $data['mime_type'],
                'original_filename' => $data['original_filename'] ?? null,
                'size_bytes' => $data['size_bytes'],
                'storage_disk' => 'media',
                'status' => MediaStatus::Pending,
            ]);

            $session = UploadSession::query()->create([
                'media_id' => $media->id,
                'user_id' => $user->id,
                'status' => UploadSessionStatus::Open,
                'expires_at' => now()->addHour(),
            ]);

            return ['media' => $media, 'session' => $session];
        });
    }
}
