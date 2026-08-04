<?php

namespace App\Models;

use App\Enums\UserTheme;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UserProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'display_name',
        'bio',
        'avatar_path',
        'avatar_disk',
        'avatar_content_iv',
        'avatar_mime_type',
        'avatar_checksum_sha256',
        'avatar_size_bytes',
        'avatar_encrypted_size_bytes',
        'locale',
        'theme',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'theme' => UserTheme::class,
            'avatar_size_bytes' => 'integer',
            'avatar_encrypted_size_bytes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasEncryptedAvatar(): bool
    {
        return $this->avatar_path !== null
            && $this->avatar_content_iv !== null
            && $this->avatar_disk !== null;
    }

    /**
     * Métadonnées publiques (jamais de clair, jamais d’URL publique du blob).
     *
     * @return array<string, mixed>|null
     */
    public function avatarMeta(): ?array
    {
        if (! $this->hasEncryptedAvatar()) {
            return null;
        }

        return [
            'mime_type' => $this->avatar_mime_type,
            'content_iv' => $this->avatar_content_iv,
            'size_bytes' => $this->avatar_size_bytes,
            'encrypted_size_bytes' => $this->avatar_encrypted_size_bytes,
            'checksum_sha256' => $this->avatar_checksum_sha256,
        ];
    }

    /**
     * @deprecated Les avatars sont chiffrés ; utiliser avatarMeta() + download API.
     */
    public function avatarUrl(): ?string
    {
        return null;
    }

    public function deleteAvatarFile(): void
    {
        if ($this->avatar_path && $this->avatar_disk) {
            Storage::disk($this->avatar_disk)->delete($this->avatar_path);
        }
    }
}
