<?php

namespace App\Http\Requests\Devices;

use App\Enums\DevicePlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RestoreWithRecoveryPhraseRequest extends FormRequest
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
            'password' => ['required', 'string'],
            'otp' => ['required', 'string', 'size:6'],
            'public_key' => ['required', 'string', 'min:32'],
            'name' => ['sometimes', 'string', 'max:120'],
            'platform' => ['sometimes', 'string', Rule::enum(DevicePlatform::class)],
        ];
    }
}
