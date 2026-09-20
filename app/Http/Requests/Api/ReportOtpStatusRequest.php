<?php

namespace App\Http\Requests\Api;

use App\Models\OtpLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportOtpStatusRequest extends FormRequest
{
    /**
     * Authorization is the device token, checked by the DeviceTokenAuth
     * middleware.
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
            'otp_id' => ['required', 'integer'],
            'status' => ['required', Rule::in(OtpLog::DEVICE_REPORTABLE_STATUSES)],
        ];
    }
}
