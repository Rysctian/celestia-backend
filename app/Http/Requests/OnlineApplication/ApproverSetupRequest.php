<?php

namespace App\Http\Requests\OnlineApplication;

use App\Models\OnlineApplication\ApproverSetup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ApproverSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Routes enforce approver_setup permissions.
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'string', 'exists:employees,employee_id'],
            'application_type' => ['required', Rule::in(ApproverSetup::APPLICATION_TYPES)],
            'approvers' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'approvers.*' => ['required', 'array:approver_id,sequence'],
            'approvers.*.approver_id' => ['required', 'string', 'exists:employees,employee_id'],
            'approvers.*.sequence' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $sequences = array_column($this->input('approvers'), 'sequence');
            sort($sequences, SORT_NUMERIC);
            if (array_map('intval', $sequences) !== range(1, count($sequences))) {
                $validator->errors()->add('approvers', 'Sequences must start at 1 and be consecutive.');
            }
        }];
    }
}
