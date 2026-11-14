<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeLogImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permissions are enforced by attendance middleware on routes/api.php.
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ];
    }
}
