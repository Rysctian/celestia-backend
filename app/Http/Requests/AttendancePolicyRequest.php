<?php

namespace App\Http\Requests;

use App\Models\AttendancePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AttendancePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Write routes enforce attendance_policy permissions via EnsureMenuAccess middleware.
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
            'name' => ['required', 'string', 'max:100', Rule::unique('attendance_policies', 'name')],
            'description' => ['nullable', 'string', 'max:255'],
            'tardy_grace_seconds' => ['required', 'integer', 'between:0,43200'],
            'tardy_count_from' => ['required', Rule::in([AttendancePolicy::COUNT_FROM_SCHEDULE_START, AttendancePolicy::COUNT_FROM_AFTER_GRACE])],
            'absent_after_late_seconds' => ['nullable', 'integer', 'between:60,86400'],
            'undertime_grace_seconds' => ['required', 'integer', 'between:0,43200'],
            'early_in_window_seconds' => ['sometimes', 'integer', 'between:0,43200'],
            'late_out_window_seconds' => ['sometimes', 'integer', 'between:0,43200'],
            'duplicate_tap_window_seconds' => ['sometimes', 'integer', 'between:0,3600'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function updateRules(): array
    {
        $policyId = $this->route('policy_id');

        return [
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('attendance_policies', 'name')->ignore($policyId)],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tardy_grace_seconds' => ['sometimes', 'integer', 'between:0,43200'],
            'tardy_count_from' => ['sometimes', Rule::in([AttendancePolicy::COUNT_FROM_SCHEDULE_START, AttendancePolicy::COUNT_FROM_AFTER_GRACE])],
            'absent_after_late_seconds' => ['sometimes', 'nullable', 'integer', 'between:60,86400'],
            'undertime_grace_seconds' => ['sometimes', 'integer', 'between:0,43200'],
            'early_in_window_seconds' => ['sometimes', 'integer', 'between:0,43200'],
            'late_out_window_seconds' => ['sometimes', 'integer', 'between:0,43200'],
            'duplicate_tap_window_seconds' => ['sometimes', 'integer', 'between:0,3600'],
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
                $validator->errors()->add('is_active', 'Default attendance policy must be active.');
            }

            if ($this->filled('absent_after_late_seconds') && $this->has('tardy_grace_seconds')) {
                if ((int) $this->input('absent_after_late_seconds') <= (int) $this->input('tardy_grace_seconds')) {
                    $validator->errors()->add(
                        'absent_after_late_seconds',
                        'Absent threshold must be greater than the tardy grace period.'
                    );
                }
            }
        }];
    }
}
