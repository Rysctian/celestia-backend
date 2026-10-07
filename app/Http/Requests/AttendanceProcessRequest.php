<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasMenuAccess('attendance', 'update');
    }

    public function rules(): array
    {
        return ['employee_id' => ['required', 'string', 'exists:employees,employee_id'], 'date' => ['required', 'date_format:Y-m-d']];
    }
}
