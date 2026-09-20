<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('project')) ?? false;
    }

    /**
     * Key material is generated server side, so there is nothing to validate
     * beyond ownership of the project.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
