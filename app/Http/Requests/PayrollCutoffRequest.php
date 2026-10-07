<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayrollCutoffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'schedule_type' => ['required', Rule::in(['monthly', 'semi-monthly'])],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'no_dtr' => ['required', 'boolean'],
            'dtr_cutoff_from' => ['required_unless:no_dtr,true', 'nullable', 'date_format:Y-m-d'],
            'dtr_cutoff_to' => ['required_unless:no_dtr,true', 'nullable', 'date_format:Y-m-d', 'after_or_equal:dtr_cutoff_from'],
            'dtr_confirmation_start' => ['required', 'date_format:Y-m-d'],
            'dtr_confirmation_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:dtr_confirmation_start'],
            'dtr_confirmation_time_from' => ['required', 'date_format:H:i'],
            'dtr_confirmation_time_to' => ['required', 'date_format:H:i'],
            'is_active' => ['sometimes', 'boolean'],
            'releases' => ['required', 'array', 'min:1', 'max:2'],
            'releases.*' => ['required', 'array:cutoff_no,release_date'],
            'releases.*.cutoff_no' => ['required', 'integer', 'between:1,2', 'distinct'],
            'releases.*.release_date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
