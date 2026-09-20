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
        ];
    }

    public function status(): ?string
    {
        return $this->validated('status');
    }
}
