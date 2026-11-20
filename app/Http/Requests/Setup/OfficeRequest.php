<?php

namespace App\Http\Requests\Setup;

use App\Models\Setup\Office;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OfficeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Write routes enforce organizational_setup permissions via EnsureMenuAccess middleware.
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
            'code' => ['required', 'string', 'max:30', Rule::unique('offices', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'type' => ['sometimes', 'string', Rule::in(Office::TYPES)],
            'parent_id' => ['nullable', 'integer', 'exists:offices,id'],
            'head_employee_id' => ['nullable', 'string', 'max:30', 'exists:employees,employee_id'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function updateRules(): array
    {
        $officeId = $this->route('office_id');

        return [
            'code' => ['sometimes', 'string', 'max:30', Rule::unique('offices', 'code')->ignore($officeId)],
            'name' => ['sometimes', 'string', 'max:150'],
            'short_name' => ['sometimes', 'nullable', 'string', 'max:50'],
            'type' => ['sometimes', 'string', Rule::in(Office::TYPES)],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:offices,id'],
            'head_employee_id' => ['sometimes', 'nullable', 'string', 'max:30', 'exists:employees,employee_id'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
