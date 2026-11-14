<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permissions are enforced by attendance middleware on routes/api.php.
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'string', 'max:30', 'exists:employees,employee_id'],
            'logged_at' => ['nullable', 'date', 'required_without_all:date,am_in,am_out,pm_in,pm_out'],
            'date' => ['nullable', 'date', 'required_without:logged_at'],
            'am_in' => ['nullable', 'date_format:H:i'],
            'am_out' => ['nullable', 'date_format:H:i'],
            'pm_in' => ['nullable', 'date_format:H:i'],
            'pm_out' => ['nullable', 'date_format:H:i'],
            'device_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
