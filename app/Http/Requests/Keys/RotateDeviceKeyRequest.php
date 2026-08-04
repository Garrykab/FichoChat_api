<?php

namespace App\Http\Requests\Keys;

use Illuminate\Foundation\Http\FormRequest;

class RotateDeviceKeyRequest extends FormRequest
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
            'public_key' => ['required', 'string', 'min:32'],
            'actor_device_id' => ['required', 'uuid'],
        ];
    }
}
