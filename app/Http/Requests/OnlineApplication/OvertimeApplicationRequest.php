<?php

namespace App\Http\Requests\OnlineApplication;

use Illuminate\Foundation\Http\FormRequest;

class OvertimeApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route permission and service ownership checks apply.
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['sometimes', 'required', 'string', 'exists:employees,employee_id'],
            'reason' => ['required', 'string', 'max:5000'],
            'status' => ['prohibited'],
            'submitted_at' => ['prohibited'],
            'details' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'details.*' => ['required', 'array:date,start_time,end_time,break_seconds'],
            'details.*.date' => ['required', 'date_format:Y-m-d'],
            'details.*.start_time' => ['required', 'date_format:H:i'],
            'details.*.end_time' => ['required', 'date_format:H:i', 'after:details.*.start_time'],
            'details.*.break_seconds' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
