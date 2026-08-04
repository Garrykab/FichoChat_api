<?php

namespace App\Http\Requests\Devices;

use App\Enums\DevicePlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterDeviceRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'platform' => ['required', Rule::enum(DevicePlatform::class)],
            'public_key' => ['required', 'string', 'min:32', 'max:10000'],
        ];
    }
}
