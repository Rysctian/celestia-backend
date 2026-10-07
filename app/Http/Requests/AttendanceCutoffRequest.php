<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AttendanceCutoffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $action = $this->routeIs('attendance-cutoffs.store') ? 'create' : 'update';

        return $this->user()->hasMenuAccess('attendance', $action);
    }

    public function rules(): array
    {
        if ($this->routeIs('attendance-cutoffs.reopen')) {
            return ['reason' => ['required', 'string', 'max:2000']];
        }
        if ($this->routeIs('attendance-cutoffs.confirm')) {
            return [];
        }

        return [
            'employee_id' => ['required', 'string', 'exists:employees,employee_id'],
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->routeIs('attendance-cutoffs.store') && $validator->errors()->isEmpty()
                && CarbonImmutable::parse($this->input('date_from'))->diffInDays(CarbonImmutable::parse($this->input('date_to'))) > 365) {
                $validator->errors()->add('date_to', 'Request no more than 366 days at a time.');
            }
        }];
    }
}
