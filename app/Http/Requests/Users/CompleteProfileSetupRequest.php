<?php

namespace App\Http\Requests\Users;

use App\Support\ValidatesEncryptedAvatar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteProfileSetupRequest extends FormRequest
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
        /** @var \App\Models\User $user */
        $user = $this->user();

        return array_merge([
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:80'],
        ], $this->encryptedAvatarRules());
    }
}
