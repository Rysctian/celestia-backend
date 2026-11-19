<?php

namespace App\Http\Requests\OnlineApplication;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeLeaveCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->method()) {
            'POST' => [
                'employee_id' => ['required', 'string', 'exists:employees,employee_id'],
                'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
                'year' => ['required', 'integer', 'between:2020,2100'],
                'allocated_credits' => ['required', 'numeric', 'between:0,365'],
                'remarks' => ['nullable', 'string', 'max:255'],
            ],
            'PUT' => [
                'allocated_credits' => ['required', 'numeric', 'between:0,365'],
                'remarks' => ['nullable', 'string', 'max:255'],
            ],
            default => [],
        };
    }
}
