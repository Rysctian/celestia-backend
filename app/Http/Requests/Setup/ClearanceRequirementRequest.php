<?php

namespace App\Http\Requests\Setup;

use App\Models\Setup\ClearanceRequirement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClearanceRequirementRequest extends FormRequest
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
        $clearanceTypeId = $this->input('clearance_type_id');

        return [
            'clearance_type_id' => ['required', 'integer', 'exists:clearance_types,id'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('clearance_requirements', 'code')->where(
                    fn ($query) => $query->where('clearance_type_id', $clearanceTypeId)
                ),
            ],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'office_id' => ['nullable', 'integer', 'exists:offices,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'is_mandatory' => ['sometimes', 'boolean'],
            'requires_attachment' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function updateRules(): array
    {
        $requirementId = $this->route('requirement_id');
        $clearanceTypeId = $this->input('clearance_type_id')
            ?? ClearanceRequirement::where('id', $requirementId)->value('clearance_type_id');

        return [
            'clearance_type_id' => ['sometimes', 'integer', 'exists:clearance_types,id'],
            'code' => [
                'sometimes',
                'string',
                'max:30',
                Rule::unique('clearance_requirements', 'code')
                    ->where(fn ($query) => $query->where('clearance_type_id', $clearanceTypeId))
                    ->ignore($requirementId),
            ],
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'office_id' => ['sometimes', 'nullable', 'integer', 'exists:offices,id'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'is_mandatory' => ['sometimes', 'boolean'],
            'requires_attachment' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
