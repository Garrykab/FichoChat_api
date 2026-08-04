<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RefreshTokenRequest extends FormRequest
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
        // Body optionnel : le Web peut n’envoyer que le cookie HttpOnly.
        return [
            'refresh_token' => ['nullable', 'string'],
        ];
    }
}
