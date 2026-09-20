<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SendOtpRequest extends FormRequest
{
    /**
     * Turkmen MSISDN: +993 followed by exactly 8 digits.
     */
    public const PHONE_PATTERN = '/^\+993\d{8}$/';

    /**
     * Authorization is the API key, checked by the ApiKeyAuth middleware.
     */
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
            'phone' => ['required', 'string', 'regex:'.self::PHONE_PATTERN],
            // Необязательный отправитель: номер одной из SIM проекта.
            'from' => ['nullable', 'string', 'regex:'.self::PHONE_PATTERN],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'The phone must be in +993XXXXXXXX format.',
            'from.regex' => 'The from must be in +993XXXXXXXX format.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Принимаем "+993 65 123456" и подобное: правило должно видеть цифры.
        foreach (['phone', 'from'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => preg_replace('/[\s\-()]/', '', $this->input($field))]);
            }
        }
    }
}
