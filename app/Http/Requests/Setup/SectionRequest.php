<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SectionRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:30', Rule::unique('sections', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'head_employee_id' => ['nullable', 'string', 'max:30', 'exists:employees,employee_id'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function updateRules(): array
    {
        $sectionId = $this->route('section_id');

        return [
            'code' => ['sometimes', 'string', 'max:30', Rule::unique('sections', 'code')->ignore($sectionId)],
            'name' => ['sometimes', 'string', 'max:150'],
            'short_name' => ['sometimes', 'nullable', 'string', 'max:50'],
            'division_id' => ['sometimes', 'nullable', 'integer', 'exists:divisions,id'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'head_employee_id' => ['sometimes', 'nullable', 'string', 'max:30', 'exists:employees,employee_id'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
