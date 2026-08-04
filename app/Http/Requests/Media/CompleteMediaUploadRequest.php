<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;

class CompleteMediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content_iv' => ['required', 'string'],
            'checksum_sha256' => ['required', 'string', 'size:64'],
            'uploader_device_id' => ['required', 'uuid'],
            'envelopes' => ['required', 'array', 'min:1'],
            'envelopes.*.recipient_device_id' => ['required', 'uuid', 'distinct'],
            'envelopes.*.encrypted_cek' => ['required', 'string'],
            'envelopes.*.ephemeral_public_key' => ['required', 'string'],
            'envelopes.*.iv' => ['required', 'string'],
            'envelopes.*.algorithm' => ['sometimes', 'string', 'max:64'],
            'envelopes.*.key_version' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
