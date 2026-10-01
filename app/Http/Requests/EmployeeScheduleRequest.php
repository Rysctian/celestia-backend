<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** @ignoreSchema */
class EmployeeScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (in_array($this->route()->getActionMethod(), ['index', 'find'], true)) {
            return $this->user()->can('manage-schedules')
                || $this->user()->employee_id === $this->route('employee_id');
        }

        return $this->user()->can('manage-schedules');
    }

    public function rules(): array
    {
        return match ($this->method()) {
            'GET' => $this->findRules(),
            'POST' => $this->storeRules(),
            'PUT' => $this->updateRules(),
            default => [],
        };
    }

    private function findRules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    private function storeRules(): array
    {
        return [
            'employee_ids' => ['required', 'array', 'min:1', 'max:100'],
            'employee_ids.*' => ['required', 'string', 'distinct', 'exists:employees,employee_id'],
            'schedule_id' => ['required', 'integer', 'exists:schedules,id'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
        ];
    }

    private function updateRules(): array
    {
        return ['effective_to' => ['required', 'date_format:Y-m-d']];
    }

    public function after(): array
    {
        if ($this->route()->getActionMethod() !== 'find') {
            return [];
        }

        return [function (Validator $validator) {
            if ($validator->errors()->isEmpty() && CarbonImmutable::parse($this->input('from'))->diffInDays(CarbonImmutable::parse($this->input('to'))) > 365) {
                $validator->errors()->add('to', 'Request no more than 366 days at a time.');
            }
        }];
    }
}
