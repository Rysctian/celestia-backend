<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

class ClearanceSignatoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'clearance_type_id' => ['required', 'integer', 'exists:clearance_types,id'],
            'clearance_requirement_id' => ['nullable', 'integer', 'exists:clearance_requirements,id'],
            'office_id' => ['nullable', 'integer', 'exists:offices,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'employee_id' => ['nullable', 'string', 'max:30', 'exists:employees,employee_id'],
            'signatory_label' => ['required', 'string', 'max:150'],
            'sign_order' => ['sometimes', 'integer', 'between:1,999'],
            'is_required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function updateRules(): array
    {
        return [
            'clearance_type_id' => ['sometimes', 'integer', 'exists:clearance_types,id'],
            'clearance_requirement_id' => ['sometimes', 'nullable', 'integer', 'exists:clearance_requirements,id'],
            'office_id' => ['sometimes', 'nullable', 'integer', 'exists:offices,id'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'position_id' => ['sometimes', 'nullable', 'integer', 'exists:positions,id'],
            'employee_id' => ['sometimes', 'nullable', 'string', 'max:30', 'exists:employees,employee_id'],
            'signatory_label' => ['sometimes', 'string', 'max:150'],
            'sign_order' => ['sometimes', 'integer', 'between:1,999'],
            'is_required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
