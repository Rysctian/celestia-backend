<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'code' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('roles', 'code')],
                'name' => ['required', 'string', 'max:100'],
            ];
        }

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            return [
                'code' => ['prohibited'],
                'name' => ['required', 'string', 'max:100'],
            ];
        }

        if ($this->isMethod('DELETE')) {
            return [
                'id' => ['required', 'integer', 'exists:roles,id'],
            ];
        }

        return [];
    }
}
