<?php

namespace App\Http\Requests\OnlineApplication;

use App\Models\OnlineApplication\LeavePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LeavePolicyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100', Rule::unique('leave_policies', 'name')],
            'description' => ['nullable', 'string', 'max:255'],
            'min_notice_days' => ['required', 'integer', 'between:0,365'],
            'allow_backdated_filing' => ['required', 'boolean'],
            'max_backdated_days' => ['nullable', 'integer', 'between:1,365'],
            'max_consecutive_days' => ['nullable', 'integer', 'between:1,365'],
            'allow_half_day' => ['required', 'boolean'],
            'accrual_mode' => ['required', Rule::in(LeavePolicy::ACCRUAL_MODES)],
            'allow_negative_balance' => ['required', 'boolean'],
            'carry_over_enabled' => ['required', 'boolean'],
            'carry_over_max_days' => ['required', 'numeric', 'between:0,365'],
            'count_rest_days' => ['required', 'boolean'],
            'allow_attachments' => ['required', 'boolean'],
            'require_attachment' => ['required', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function updateRules(): array
    {
        $policyId = $this->route('policy_id');

        return [
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('leave_policies', 'name')->ignore($policyId)],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'min_notice_days' => ['sometimes', 'integer', 'between:0,365'],
            'allow_backdated_filing' => ['sometimes', 'boolean'],
            'max_backdated_days' => ['sometimes', 'nullable', 'integer', 'between:1,365'],
            'max_consecutive_days' => ['sometimes', 'nullable', 'integer', 'between:1,365'],
            'allow_half_day' => ['sometimes', 'boolean'],
            'accrual_mode' => ['sometimes', Rule::in(LeavePolicy::ACCRUAL_MODES)],
            'allow_negative_balance' => ['sometimes', 'boolean'],
            'carry_over_enabled' => ['sometimes', 'boolean'],
            'carry_over_max_days' => ['sometimes', 'numeric', 'between:0,365'],
            'count_rest_days' => ['sometimes', 'boolean'],
            'allow_attachments' => ['sometimes', 'boolean'],
            'require_attachment' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->boolean('is_default') && $this->has('is_active') && ! $this->boolean('is_active')) {
                $validator->errors()->add('is_active', 'Default leave policy must be active.');
            }

            if ($this->has('allow_attachments') && ! $this->boolean('allow_attachments') && $this->boolean('require_attachment')) {
                $validator->errors()->add('require_attachment', 'Attachment cannot be required when attachments are disabled.');
            }

            if ($this->has('allow_backdated_filing')) {
                $allowBackdated = $this->boolean('allow_backdated_filing');

                if (! $allowBackdated && $this->filled('max_backdated_days')) {
                    $validator->errors()->add('max_backdated_days', 'Max backdated days must be empty when backdated filing is disabled.');
                }

                if ($allowBackdated && (int) $this->input('min_notice_days', 0) > 0) {
                    $validator->errors()->add('min_notice_days', 'Minimum notice days must be 0 when backdated filing is enabled.');
                }
            }

            if ($this->has('carry_over_enabled') && ! $this->boolean('carry_over_enabled') && (float) $this->input('carry_over_max_days', 0) > 0) {
                $validator->errors()->add('carry_over_max_days', 'Carry-over max days must be 0 when carry-over is disabled.');
            }
        }];
    }
}
