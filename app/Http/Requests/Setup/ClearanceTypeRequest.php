<?php

namespace App\Http\Requests\Setup;

use App\Models\Setup\ClearanceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClearanceTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Write routes enforce administrative_setup permissions via EnsureMenuAccess middleware.
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
            'code' => ['required', 'string', 'max:30', Rule::unique('clearance_types', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'applies_to' => ['sometimes', 'string', Rule::in(ClearanceType::APPLIES_TO_OPTIONS)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function updateRules(): array
    {
        $clearanceTypeId = $this->route('clearance_type_id');

        return [
            'code' => ['sometimes', 'string', 'max:30', Rule::unique('clearance_types', 'code')->ignore($clearanceTypeId)],
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'applies_to' => ['sometimes', 'string', Rule::in(ClearanceType::APPLIES_TO_OPTIONS)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
