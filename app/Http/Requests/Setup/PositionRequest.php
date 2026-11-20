<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PositionRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:30', Rule::unique('positions', 'code')],
            'title' => ['required', 'string', 'max:150'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'reports_to_position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'salary_grade' => ['nullable', 'string', 'max:30'],
            'job_level' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function updateRules(): array
    {
        $positionId = $this->route('position_id');

        return [
            'code' => ['sometimes', 'string', 'max:30', Rule::unique('positions', 'code')->ignore($positionId)],
            'title' => ['sometimes', 'string', 'max:150'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'division_id' => ['sometimes', 'nullable', 'integer', 'exists:divisions,id'],
            'section_id' => ['sometimes', 'nullable', 'integer', 'exists:sections,id'],
            'reports_to_position_id' => ['sometimes', 'nullable', 'integer', 'exists:positions,id'],
            'salary_grade' => ['sometimes', 'nullable', 'string', 'max:30'],
            'job_level' => ['sometimes', 'nullable', 'string', 'max:50'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
