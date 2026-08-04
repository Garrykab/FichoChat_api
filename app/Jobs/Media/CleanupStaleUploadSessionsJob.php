<?php

namespace App\Jobs\Media;

use App\Enums\MediaStatus;
use App\Enums\UploadSessionStatus;
use App\Models\UploadSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Expire les sessions d'upload ouvertes dépassées et marque les médias associés en échec (PRD §26.10).
 */
class CleanupStaleUploadSessionsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $limit = 100)
    {
        $this->onQueue('media');
    }

    public function handle(): void
    {
        $sessions = UploadSession::query()
            ->with('media')
            ->where('status', UploadSessionStatus::Open)
            ->where('expires_at', '<', now())
            ->orderBy('expires_at')
            ->limit($this->limit)
            ->get();

        foreach ($sessions as $session) {
            $session->update(['status' => UploadSessionStatus::Expired]);

            $media = $session->media;
            if ($media === null) {
                continue;
            }

            if (! in_array($media->status, [MediaStatus::Pending, MediaStatus::Uploading], true)) {
                continue;
            }

            if ($media->storage_path) {
                Storage::disk($media->storage_disk)->delete($media->storage_path);
            }

            $media->update([
                'status' => MediaStatus::Failed,
                'storage_path' => null,
            ]);
        }
    }
}
