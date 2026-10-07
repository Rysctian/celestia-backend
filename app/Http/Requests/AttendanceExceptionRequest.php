<?php

namespace App\Http\Requests;

use App\Models\AttendanceException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasMenuAccess('attendance', 'update');
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'string', 'exists:employees,employee_id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', Rule::in(AttendanceException::TYPES)],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
