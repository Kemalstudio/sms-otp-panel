<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Public endpoint: the pairing code itself is the credential, so there is
 * nothing to authorize here.
 */
class PairDeviceRequest extends FormRequest
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
            'pairing_code' => ['required', 'string', 'size:6'],
            'device_name' => ['required', 'string', 'min:2', 'max:255'],
            'fcm_token' => ['required', 'string', 'max:255'],
            // Номер SIM этого телефона. Необязательный: Android не даёт
            // прочитать его надёжно, поэтому вводит оператор, а он может и
            // отложить это до панели.
            'phone_number' => ['nullable', 'string', 'max:32'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('pairing_code'))) {
            $this->merge(['pairing_code' => strtoupper(trim($this->input('pairing_code')))]);
        }

        if (is_string($this->input('phone_number'))) {
            $normalized = preg_replace('/[\s\-()]/', '', $this->input('phone_number'));
            $this->merge(['phone_number' => $normalized === '' ? null : $normalized]);
        }
    }
}
