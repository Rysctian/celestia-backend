<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasMenuAccess('attendance', 'create');
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'string', 'exists:employees,employee_id'],
            'logged_at' => ['required', 'date', 'regex:/T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/'],
            'source' => ['required', 'string', 'max:30'],
            'device_id' => ['nullable', 'string', 'max:100'],
            'external_log_id' => ['nullable', 'string', 'max:150'],
            'raw_payload' => ['nullable', 'array'],
            'log_type' => ['prohibited'],
        ];
    }
}
