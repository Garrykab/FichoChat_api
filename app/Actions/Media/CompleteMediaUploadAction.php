<?php

namespace App\Actions\Media;

use App\Actions\Keys\StoreKeyEnvelopesAction;
use App\Enums\EnvelopeContentType;
use App\Enums\MediaStatus;
use App\Enums\UploadSessionStatus;
use App\Enums\ActivityLogType;
use App\Models\Media;
use App\Models\UploadSession;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteMediaUploadAction
{
    public function __construct(
        private readonly StoreKeyEnvelopesAction $storeKeyEnvelopesAction,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{
     *     content_iv: string,
     *     checksum_sha256: string,
     *     uploader_device_id: string,
     *     envelopes: list<array<string, mixed>>
     * }  $data
     */
    public function execute(User $user, UploadSession $session, array $data)
    {
        if ($session->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'session' => ['Upload session does not belong to you.'],
            ]);
        }

        if ($session->status !== UploadSessionStatus::Open) {
            throw ValidationException::withMessages([
                'session' => ['Upload session cannot be completed.'],
            ]);
        }

        $media = $session->media;

        if ($media->storage_path === null || $media->encrypted_size_bytes < 1) {
            throw ValidationException::withMessages([
                'session' => ['Encrypted content must be uploaded before completion.'],
            ]);
        }

        if (strtolower($data['checksum_sha256']) !== $media->checksum_sha256 && $media->checksum_sha256 !== null) {
            // optional pre-set; client provides authoritative checksum at complete
        }

        $ready = DB::transaction(function () use ($user, $session, $media, $data) {
            $media->forceFill([
                'content_iv' => $data['content_iv'],
                'checksum_sha256' => strtolower($data['checksum_sha256']),
                'status' => MediaStatus::Ready,
            ])->save();

            $this->storeKeyEnvelopesAction->execute($user, [
                'content_type' => EnvelopeContentType::Media->value,
                'content_id' => $media->id,
                'sender_device_id' => $data['uploader_device_id'],
                'envelopes' => $data['envelopes'],
            ]);

            $session->forceFill([
                'status' => UploadSessionStatus::Completed,
            ])->save();

            return $media->fresh();
        });

        $this->auditLogger->activity(
            ActivityLogType::MediaUploaded,
            $user,
            [
                'mime_type' => $ready->mime_type,
                'encrypted_size_bytes' => $ready->encrypted_size_bytes,
            ],
            Media::class,
            $ready->id,
            deviceId: $data['uploader_device_id'],
        );

        return $ready;
    }
}
