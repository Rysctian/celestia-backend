<?php

namespace App\Http\Requests\OnlineApplication;

use App\Models\OnlineApplication\OfficialBusinessApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OfficialBusinessApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route permission and service ownership checks apply.
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['sometimes', 'nullable', 'string', 'exists:employees,employee_id'],
            'employee_ids' => ['sometimes', 'array'],
            'employee_ids.*' => ['string', 'exists:employees,employee_id'],
            'allow_approver' => ['sometimes', 'boolean'],
            'auto_approved' => ['sometimes', 'boolean'],
            'ob_type' => ['required', 'string', Rule::in(OfficialBusinessApplication::OB_TYPES)],
            'destination' => ['nullable', 'required_if:ob_type,ob', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:5000'],
            'status' => ['prohibited'],
            'submitted_at' => ['prohibited'],
            'details' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'details.*' => ['required', 'array:date,start_time,end_time'],
            'details.*.date' => ['required', 'date_format:Y-m-d'],
            'details.*.start_time' => ['required', 'date_format:H:i'],
            'details.*.end_time' => ['required', 'date_format:H:i', 'after:details.*.start_time'],
        ];
    }
}
