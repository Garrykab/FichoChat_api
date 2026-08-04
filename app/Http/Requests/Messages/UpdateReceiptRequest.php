<?php

namespace App\Http\Requests\Messages;

use App\Enums\ReceiptStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReceiptRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in([ReceiptStatus::Delivered->value, ReceiptStatus::Read->value])],
            'device_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
