<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CutOffRequest extends FormRequest
{
    public function authorize(): bool
    {
        // All write routes enforce the cut_off menu permission.
        return true;
    }

    public function rules(): array
    {
        return [
            'schedule_type' => ['required', Rule::in(['monthly', 'semi_monthly'])],
            'name' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'between:1900,9999'],
            'month' => ['required', 'integer', 'between:1,12'],
            'is_active' => ['sometimes', 'boolean'],
            'quarter' => ['prohibited'],
            'releases' => ['prohibited'],
            'periods' => ['required', 'array', 'min:1', 'max:2'],
            'periods.*' => ['required', 'array:cut_off_no,dtr_date_from,dtr_date_to,no_dtr,confirmation_start_date,confirmation_end_date,confirmation_time_from,confirmation_time_to,payroll_date_from,payroll_date_to,release_date'],
            'periods.*.cut_off_no' => ['required', 'integer', Rule::in([1, 2]), 'distinct'],
            'periods.*.no_dtr' => ['required', 'boolean'],
            'periods.*.dtr_date_from' => ['exclude_if:periods.*.no_dtr,true', 'required', 'date_format:Y-m-d'],
            'periods.*.dtr_date_to' => ['exclude_if:periods.*.no_dtr,true', 'required', 'date_format:Y-m-d', 'after_or_equal:periods.*.dtr_date_from'],
            'periods.*.confirmation_start_date' => ['exclude_if:periods.*.no_dtr,true', 'required', 'date_format:Y-m-d'],
            'periods.*.confirmation_end_date' => ['exclude_if:periods.*.no_dtr,true', 'required', 'date_format:Y-m-d', 'after_or_equal:periods.*.confirmation_start_date'],
            'periods.*.confirmation_time_from' => ['exclude_if:periods.*.no_dtr,true', 'required', 'date_format:H:i'],
            'periods.*.confirmation_time_to' => ['exclude_if:periods.*.no_dtr,true', 'required', 'date_format:H:i'],
            'periods.*.payroll_date_from' => ['required', 'date_format:Y-m-d'],
            'periods.*.payroll_date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:periods.*.payroll_date_from'],
            'periods.*.release_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:periods.*.payroll_date_to'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach ($this->input('periods', []) as $index => $period) {
                if ($period['no_dtr']) {
                    continue;
                }

                $start = CarbonImmutable::parse($period['confirmation_start_date'].' '.$period['confirmation_time_from']);
                $end = CarbonImmutable::parse($period['confirmation_end_date'].' '.$period['confirmation_time_to']);
                if ($end->lessThan($start)) {
                    $validator->errors()->add("periods.$index.confirmation_time_to", 'Confirmation must end on or after its start date and time.');
                }
            }
        }];
    }
}
