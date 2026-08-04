<?php

namespace App\Support;

trait ValidatesEncryptedAvatar
{
    /**
     * @return array<string, mixed>
     */
    protected function encryptedAvatarRules(): array
    {
        return [
            'avatar' => ['sometimes', 'nullable', 'file', 'max:5120'],
            'avatar_content_iv' => ['required_with:avatar', 'string', 'max:255'],
            'avatar_checksum_sha256' => ['required_with:avatar', 'string', 'size:64'],
            'avatar_mime_type' => ['required_with:avatar', 'string', 'max:127'],
            'avatar_size_bytes' => ['required_with:avatar', 'integer', 'min:1', 'max:5242880'],
            'uploader_device_id' => ['required_with:avatar', 'uuid'],
            'envelopes' => ['required_with:avatar', 'array', 'min:1'],
            'envelopes.*.recipient_device_id' => ['required', 'uuid'],
            'envelopes.*.encrypted_cek' => ['required', 'string'],
            'envelopes.*.ephemeral_public_key' => ['required', 'string'],
            'envelopes.*.iv' => ['required', 'string'],
            'envelopes.*.algorithm' => ['sometimes', 'string', 'max:64'],
            'envelopes.*.key_version' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
