<?php

namespace App\Http\Requests\Devices;

use Illuminate\Foundation\Http\FormRequest;

class UpsertRecoveryBackupRequest extends FormRequest
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
            'ciphertext' => ['required', 'string'],
            'salt' => ['required', 'string', 'max:128'],
            'iv' => ['required', 'string', 'max:64'],
            'kdf' => ['required', 'string', 'in:pbkdf2-sha256'],
            'kdf_iterations' => ['required', 'integer', 'min:100000', 'max:1000000'],
            'public_key_fingerprint' => ['required', 'string', 'size:64'],
            'version' => ['sometimes', 'integer', 'min:1', 'max:255'],
        ];
    }
}
