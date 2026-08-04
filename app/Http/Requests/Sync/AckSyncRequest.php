<?php

namespace App\Http\Requests\Sync;

use Illuminate\Foundation\Http\FormRequest;

class AckSyncRequest extends FormRequest
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
            'device_id' => ['required', 'uuid'],
            'cursor_at' => ['required', 'date'],
            'last_message_id' => ['sometimes', 'nullable', 'uuid'],
            'bootstrap_completed' => ['sometimes', 'boolean'],
        ];
    }
}
