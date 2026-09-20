<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('project')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Пустое значение выключает вебхуки — это штатный способ, а не ошибка.
            'webhook_url' => ['nullable', 'string', 'url', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'webhook_url.url' => 'Адрес должен быть полным URL, например https://api.example.com/hooks/otp.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('webhook_url'))) {
            $trimmed = trim($this->input('webhook_url'));
            $this->merge(['webhook_url' => $trimmed === '' ? null : $trimmed]);
        }
    }
}
