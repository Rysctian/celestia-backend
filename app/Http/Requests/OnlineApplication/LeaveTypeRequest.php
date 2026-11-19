<?php

namespace App\Http\Requests\OnlineApplication;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaveTypeRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:20', Rule::unique('leave_types', 'code')],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'leave_policy_id' => ['nullable', 'integer', 'exists:leave_policies,id'],
            'is_paid' => ['required', 'boolean'],
            'requires_credits' => ['required', 'boolean'],
            'default_credits' => ['required', 'numeric', 'between:0,365'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function updateRules(): array
    {
        $typeId = $this->route('leave_type_id');

        return [
            'code' => ['sometimes', 'string', 'max:20', Rule::unique('leave_types', 'code')->ignore($typeId)],
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'leave_policy_id' => ['sometimes', 'nullable', 'integer', 'exists:leave_policies,id'],
            'is_paid' => ['sometimes', 'boolean'],
            'requires_credits' => ['sometimes', 'boolean'],
            'default_credits' => ['sometimes', 'numeric', 'between:0,365'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
