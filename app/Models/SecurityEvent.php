<?php

namespace App\Models;

use App\Enums\LogSeverity;
use App\Enums\SecurityEventType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'device_id',
        'type',
        'severity',
        'module',
        'result',
        'ip_address',
        'user_agent',
        'context',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SecurityEventType::class,
            'severity' => LogSeverity::class,
            'context' => 'array',
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
}
