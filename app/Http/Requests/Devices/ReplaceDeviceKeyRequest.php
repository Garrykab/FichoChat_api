<?php

namespace App\Http\Requests\Devices;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceDeviceKeyRequest extends FormRequest
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
            'actor_device_id' => ['required', 'uuid'],
            'public_key' => ['required', 'string', 'min:32'],
        ];
    }
}
