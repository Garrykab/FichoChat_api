<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceRecoveryBackup extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'ciphertext',
        'salt',
        'iv',
        'kdf',
        'kdf_iterations',
        'public_key_fingerprint',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kdf_iterations' => 'integer',
            'version' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
