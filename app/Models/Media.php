<?php

namespace App\Models;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Media extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'medias';

    protected $fillable = [
        'conversation_id',
        'message_id',
        'uploader_user_id',
        'uploader_device_id',
        'type',
        'mime_type',
        'original_filename',
        'size_bytes',
        'encrypted_size_bytes',
        'checksum_sha256',
        'storage_disk',
        'storage_path',
        'content_iv',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'status' => MediaStatus::class,
            'size_bytes' => 'integer',
            'encrypted_size_bytes' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_user_id');
    }

    public function uploadSession(): HasOne
    {
        return $this->hasOne(UploadSession::class);
    }

    public function isReady(): bool
    {
        return $this->status === MediaStatus::Ready;
    }
}
