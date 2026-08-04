<?php

namespace App\Models;

use App\Enums\SyncStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceSyncState extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'device_id',
        'status',
        'cursor_at',
        'last_message_id',
        'bootstrap_completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SyncStatus::class,
            'cursor_at' => 'datetime',
            'bootstrap_completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'device_id');
    }

    public function needsBootstrap(): bool
    {
        return $this->status === SyncStatus::PendingBootstrap
            || $this->bootstrap_completed_at === null;
    }
}
