<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppSettingsRequest extends FormRequest
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
            'media' => ['sometimes', 'array'],
            'media.max_files' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'media.max_file_bytes' => ['sometimes', 'integer', 'min:1024', 'max:524288000'],
            'media.max_total_bytes' => ['sometimes', 'integer', 'min:1024', 'max:1048576000'],
            'media.chunk_bytes' => ['sometimes', 'integer', 'min:65536', 'max:10485760'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $media = $this->input('media');
            if (! is_array($media)) {
                return;
            }

            $maxFile = isset($media['max_file_bytes']) ? (int) $media['max_file_bytes'] : null;
            $maxTotal = isset($media['max_total_bytes']) ? (int) $media['max_total_bytes'] : null;

            if ($maxFile !== null && $maxTotal !== null && $maxTotal < $maxFile) {
                $validator->errors()->add(
                    'media.max_total_bytes',
                    'max_total_bytes must be greater than or equal to max_file_bytes.',
                );
            }
        });
    }
}
