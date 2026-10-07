<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasMenuAccess('attendance', 'view')
            || ($this->user()->employee_id === $this->input('employee_id') && $this->user()->hasMenuAccess('employee_201', 'view'));
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'string', 'exists:employees,employee_id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'status' => ['nullable', 'string', 'max:30'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0'],
            'sort_by' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'in:asc,desc'],
        ];
    }
}
