<?php

namespace App\Models;

use App\Enums\DevicePlatform;
use App\Enums\DeviceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserDevice extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'platform',
        'public_key',
        'fingerprint',
        'status',
        'approved_at',
        'approved_by_device_id',
        'revoked_at',
        'last_seen_at',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => DevicePlatform::class,
            'status' => DeviceStatus::class,
            'approved_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedByDevice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'approved_by_device_id');
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class, 'device_id');
    }

    public function sentEnvelopes(): HasMany
    {
        return $this->hasMany(KeyEnvelope::class, 'sender_device_id');
    }

    public function receivedEnvelopes(): HasMany
    {
        return $this->hasMany(KeyEnvelope::class, 'recipient_device_id');
    }

    public function isApproved(): bool
    {
        return $this->status === DeviceStatus::Approved;
    }

    public function isPending(): bool
    {
        return $this->status === DeviceStatus::Pending;
    }

    public function isRevoked(): bool
    {
        return $this->status === DeviceStatus::Revoked;
    }
}
