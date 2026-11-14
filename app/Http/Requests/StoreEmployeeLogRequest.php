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
            'logged_at' => ['required', 'date'],
            'device_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
