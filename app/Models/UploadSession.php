<?php

namespace App\Models;

use App\Enums\UploadSessionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'media_id',
        'user_id',
        'status',
        'bytes_received',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UploadSessionStatus::class,
            'bytes_received' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return $this->status === UploadSessionStatus::Open && $this->expires_at->isFuture();
    }
}
