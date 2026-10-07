<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => [
                'required',
                'string',
                'exists:employees,employee_id',
            ],

            'from' => [
                'required',
                'date_format:Y-m-d',
            ],

            'to' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:from',
            ],
        ];
    }
}
