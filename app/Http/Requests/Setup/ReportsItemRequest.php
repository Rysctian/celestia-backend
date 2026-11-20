<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportsItemRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:50', Rule::unique('reports_items', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'office_id' => ['nullable', 'integer', 'exists:offices,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'reports_to_position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'reports_to_employee_id' => ['nullable', 'string', 'max:30', 'exists:employees,employee_id'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function updateRules(): array
    {
        $reportsItemId = $this->route('reports_item_id');

        return [
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('reports_items', 'code')->ignore($reportsItemId)],
            'name' => ['sometimes', 'string', 'max:150'],
            'office_id' => ['sometimes', 'nullable', 'integer', 'exists:offices,id'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'division_id' => ['sometimes', 'nullable', 'integer', 'exists:divisions,id'],
            'section_id' => ['sometimes', 'nullable', 'integer', 'exists:sections,id'],
            'position_id' => ['sometimes', 'nullable', 'integer', 'exists:positions,id'],
            'reports_to_position_id' => ['sometimes', 'nullable', 'integer', 'exists:positions,id'],
            'reports_to_employee_id' => ['sometimes', 'nullable', 'string', 'max:30', 'exists:employees,employee_id'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
