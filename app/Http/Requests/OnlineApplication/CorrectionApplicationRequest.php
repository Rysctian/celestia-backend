<?php

namespace App\Http\Requests\OnlineApplication;

use Illuminate\Foundation\Http\FormRequest;

class CorrectionApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route permission and service ownership checks apply.
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['sometimes', 'nullable', 'string', 'exists:employees,employee_id'],
            'employee_ids' => ['prohibited'],
            'allow_approver' => ['sometimes', 'boolean'],
            'auto_approved' => ['sometimes', 'boolean'],
            'reason' => ['required', 'string', 'max:5000'],
            'status' => ['prohibited'],
            'submitted_at' => ['prohibited'],
            'details' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'details.*' => ['required', 'array:date,am_in,am_out,pm_in,pm_out'],
            'details.*.date' => ['required', 'date_format:Y-m-d'],
            'details.*.am_in' => ['nullable', 'date_format:H:i'],
            'details.*.am_out' => ['nullable', 'date_format:H:i'],
            'details.*.pm_in' => ['nullable', 'date_format:H:i'],
            'details.*.pm_out' => ['nullable', 'date_format:H:i'],
        ];
    }
}
