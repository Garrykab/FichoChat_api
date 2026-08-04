<?php

namespace App\Http\Requests\Users;

use App\Enums\UserTheme;
use App\Support\ValidatesEncryptedAvatar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    use ValidatesEncryptedAvatar;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'display_name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500'],
            'locale' => ['sometimes', 'string', 'max:10'],
            'theme' => ['sometimes', Rule::enum(UserTheme::class)],
        ], $this->encryptedAvatarRules());
    }
}
