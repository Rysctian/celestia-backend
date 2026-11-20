<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    public function rules(): array
    {
        return match ($this->method()) {
            'POST' => $this->storeRules(),
            'PUT' => $this->updateRules(),
            default => [],
        };
    }

    private function storeRules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('departments', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'office_id' => ['nullable', 'integer', 'exists:offices,id'],
            'head_employee_id' => ['nullable', 'string', 'max:30', 'exists:employees,employee_id'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function updateRules(): array
    {
        $departmentId = $this->route('department_id');

        return [
            'code' => ['sometimes', 'string', 'max:30', Rule::unique('departments', 'code')->ignore($departmentId)],
            'name' => ['sometimes', 'string', 'max:150'],
            'short_name' => ['sometimes', 'nullable', 'string', 'max:50'],
            'office_id' => ['sometimes', 'nullable', 'integer', 'exists:offices,id'],
            'head_employee_id' => ['sometimes', 'nullable', 'string', 'max:30', 'exists:employees,employee_id'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
