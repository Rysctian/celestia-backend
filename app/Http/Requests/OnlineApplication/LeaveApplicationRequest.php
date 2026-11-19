<?php

namespace App\Http\Requests\OnlineApplication;

use App\Models\OnlineApplication\LeaveApplicationDetail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['sometimes', 'nullable', 'string', 'exists:employees,employee_id'],
            'employee_ids' => ['prohibited'],
            'allow_approver' => ['sometimes', 'boolean'],
            'auto_approved' => ['sometimes', 'boolean'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'reason' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:5120'],
            'status' => ['prohibited'],
            'submitted_at' => ['prohibited'],
            'details' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'details.*' => ['required', 'array:date,duration_type'],
            'details.*.date' => ['required', 'date_format:Y-m-d'],
            'details.*.duration_type' => ['required', 'string', Rule::in(LeaveApplicationDetail::DURATION_TYPES)],
        ];
    }
}
