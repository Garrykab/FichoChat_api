<?php

namespace App\Http\Requests\Media;

use App\Enums\MediaType;
use App\Services\Settings\AppSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateMediaSessionRequest extends FormRequest
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
        $maxFileBytes = app(AppSettings::class)->mediaLimits()['max_file_bytes'];

        return [
            'conversation_id' => ['required', 'uuid'],
            'uploader_device_id' => ['required', 'uuid'],
            'type' => ['required', 'string', Rule::enum(MediaType::class)],
            'mime_type' => ['required', 'string', 'max:127'],
            'original_filename' => ['sometimes', 'nullable', 'string', 'max:255'],
            'size_bytes' => ['required', 'integer', 'min:1', 'max:'.$maxFileBytes],
        ];
    }
}
