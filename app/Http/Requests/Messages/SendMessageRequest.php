<?php

namespace App\Http\Requests\Messages;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
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
            'sender_device_id' => ['required', 'uuid'],
            'ciphertext' => ['required', 'string'],
            'iv' => ['required', 'string'],
            'type' => ['sometimes', 'string', 'in:text,image,video,document,audio,voice'],
            'reply_to_message_id' => ['sometimes', 'nullable', 'uuid'],
            'media_ids' => ['sometimes', 'array', 'max:10'],
            'media_ids.*' => ['uuid', 'distinct'],
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
