<?php

namespace App\Http\Requests\Keys;

use App\Enums\EnvelopeContentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKeyEnvelopesRequest extends FormRequest
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
            'content_type' => ['required', 'string', Rule::enum(EnvelopeContentType::class)],
            'content_id' => ['required', 'uuid'],
            'sender_device_id' => ['required', 'uuid'],
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
