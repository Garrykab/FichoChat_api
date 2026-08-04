<?php

namespace App\Actions\Media;

use App\Enums\MediaStatus;
use App\Models\UploadSession;
use App\Models\User;
use App\Services\Settings\AppSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UploadMediaContentAction
{
    public function __construct(
        private readonly AppSettings $appSettings,
    ) {}

    /**
     * @param  array{start?: int, end?: int, total?: int}|null  $range
     */
    public function execute(
        User $user,
        UploadSession $session,
        UploadedFile|string $content,
        ?array $range = null,
    ): UploadSession {
        $maxEncryptedBytes = $this->appSettings->maxEncryptedBytes();

        if ($session->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'session' => ['Upload session does not belong to you.'],
            ]);
        }

        if (! $session->isOpen()) {
            throw ValidationException::withMessages([
                'session' => ['Upload session is closed or expired.'],
            ]);
        }

        if ($content instanceof UploadedFile) {
            $binary = file_get_contents($content->getRealPath()) ?: '';
        } else {
            $binary = $content;
        }

        $chunkBytes = strlen($binary);
        if ($chunkBytes === 0) {
            throw ValidationException::withMessages([
                'content' => ['Encrypted content chunk is empty.'],
            ]);
        }

        $media = $session->media;
        $disk = $media->storage_disk ?: 'media';
        $path = sprintf(
            'conversations/%s/%s.enc',
            $media->conversation_id,
            $media->id,
        );

        if ($range !== null) {
            $start = $range['start'];
            $end = $range['end'];
            $total = $range['total'];

            if ($end < $start || ($end - $start + 1) !== $chunkBytes) {
                throw ValidationException::withMessages([
                    'content_range' => ['Content-Range does not match body length.'],
                ]);
            }

            if ($total < 1 || $total > $maxEncryptedBytes) {
                throw ValidationException::withMessages([
                    'content_range' => ['Encrypted payload exceeds maximum size.'],
                ]);
            }

            if ($end >= $total) {
                throw ValidationException::withMessages([
                    'content_range' => ['Content-Range end must be less than total.'],
                ]);
            }

            if ($start !== (int) $session->bytes_received) {
                throw ValidationException::withMessages([
                    'content_range' => ['Chunks must be uploaded sequentially. Expected start '.$session->bytes_received.'.'],
                ]);
            }

            if ($start === 0) {
                Storage::disk($disk)->put($path, $binary);
            } else {
                if (! Storage::disk($disk)->exists($path)) {
                    throw ValidationException::withMessages([
                        'content' => ['Previous chunks are missing.'],
                    ]);
                }
                // separator vide : pas de PHP_EOL (blob binaire).
                Storage::disk($disk)->append($path, $binary, '');
            }

            $bytesReceived = $end + 1;
        } else {
            if ($chunkBytes > $maxEncryptedBytes) {
                throw ValidationException::withMessages([
                    'content' => ['Encrypted payload exceeds maximum size.'],
                ]);
            }
            Storage::disk($disk)->put($path, $binary);
            $bytesReceived = $chunkBytes;
        }

        $media->forceFill([
            'storage_path' => $path,
            'encrypted_size_bytes' => $bytesReceived,
            'status' => MediaStatus::Uploading,
        ])->save();

        $session->forceFill([
            'bytes_received' => $bytesReceived,
        ])->save();

        return $session->fresh('media');
    }
}
