<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserTheme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
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
            'notifications_enabled' => ['sometimes', 'boolean'],
            'notify_messages' => ['sometimes', 'boolean'],
            'notify_devices' => ['sometimes', 'boolean'],
            'notify_security' => ['sometimes', 'boolean'],
            'hide_message_previews' => ['sometimes', 'boolean'],
            'silent_mode' => ['sometimes', 'boolean'],
            'send_read_receipts' => ['sometimes', 'boolean'],
            'show_last_seen' => ['sometimes', 'boolean'],
            'auto_download_media' => ['sometimes', 'boolean'],
            'locale' => ['sometimes', 'string', Rule::in(['fr', 'en'])],
            'theme' => ['sometimes', Rule::enum(UserTheme::class)],
        ];
    }
}
