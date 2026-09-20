<?php

namespace App\Http\Requests;

use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeviceRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:255'],

            /*
             * Номер SIM уникален в пределах проекта: по нему вызывающий
             * фиксирует отправителя в /otp/send, и два телефона с одним
             * номером сделали бы этот выбор неоднозначным.
             */
            'phone_number' => [
                'nullable',
                'string',
                'regex:/^\+\d{7,15}$/',
                Rule::unique('devices', 'phone_number')
                    ->where('project_id', $this->route('project')->id)
                    ->ignore($this->route('device')->id),
            ],

            'throughput_per_minute' => [
                'required',
                'integer',
                'min:1',
                'max:'.Device::MAX_THROUGHPUT_PER_MINUTE,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone_number.regex' => 'Номер должен быть в международном формате, например +99365123456.',
            'phone_number.unique' => 'Этот номер уже закреплён за другим устройством проекта.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone_number'))) {
            $normalized = preg_replace('/[\s\-()]/', '', $this->input('phone_number'));
            $this->merge(['phone_number' => $normalized === '' ? null : $normalized]);
        }
    }
}
