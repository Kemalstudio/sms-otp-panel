<?php

namespace App\Http\Requests;

use App\Models\OtpLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexOtpLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('project')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(OtpLog::STATUSES)],
            // Поиск по номеру — самая частая операция поддержки: «клиент
            // говорит, что код не пришёл».
            'phone' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function status(): ?string
    {
        return $this->validated('status');
    }

    /** Только цифры: оператор копирует номер откуда угодно, в любом формате. */
    public function phone(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $this->validated('phone'));

        return $digits === '' ? null : $digits;
    }
}
