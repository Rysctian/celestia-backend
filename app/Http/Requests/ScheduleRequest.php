<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** @ignoreSchema */
class ScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-schedules');
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
            'name' => ['required', 'string', 'max:100'],
            'timezone' => ['nullable', 'string', 'timezone', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'details' => ['required', 'array', 'min:7'],
            'details.*' => ['required', 'array:day_of_week,is_rest_day,start_time,end_time'],
            'details.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'details.*.is_rest_day' => ['required', 'boolean'],
            'details.*.start_time' => ['present', 'nullable', 'date_format:H:i'],
            'details.*.end_time' => ['present', 'nullable', 'date_format:H:i'],
        ];
    }

    private function updateRules(): array
    {
        $rules = $this->storeRules();
        foreach (['name', 'timezone', 'details'] as $field) {
            $rules[$field][0] = 'sometimes';
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || ! $this->has('details')) {
                return;
            }

            $shifts = [];
            $weekdays = [];
            $restDays = [];
            $detailCounts = [];

            foreach ($this->input('details') as $index => $detail) {
                $weekday = (int) $detail['day_of_week'];
                $weekdays[$weekday] = true;
                $detailCounts[$weekday] = ($detailCounts[$weekday] ?? 0) + 1;

                if ($detail['is_rest_day']) {
                    $restDays[$weekday] = true;
                    if ($detail['start_time'] !== null || $detail['end_time'] !== null) {
                        $validator->errors()->add("details.$index", 'Rest days must have null times.');
                    }

                    continue;
                }

                if ($detail['start_time'] === null || $detail['end_time'] === null) {
                    $validator->errors()->add("details.$index", 'Working slots require start and end times.');

                    continue;
                }

                [$startHour, $startMinute] = explode(':', $detail['start_time']);
                [$endHour, $endMinute] = explode(':', $detail['end_time']);
                $start = $startHour * 60 + $startMinute;
                $end = $endHour * 60 + $endMinute;

                if ($end <= $start) {
                    $validator->errors()->add("details.$index", 'End time must be after start time on the same day.');
                }

                $shifts[$weekday][] = ['start' => $start, 'end' => $end];
            }

            if (count($weekdays) !== 7) {
                $validator->errors()->add('details', 'Configure all seven weekdays.');
            }

            foreach ($this->input('details') as $index => $detail) {
                if (isset($restDays[$detail['day_of_week']]) && $detailCounts[$detail['day_of_week']] > 1) {
                    $validator->errors()->add("details.$index", 'A rest day cannot have another slot.');
                }
            }

            foreach ($shifts as $dayShifts) {
                usort($dayShifts, fn ($a, $b) => $a['start'] <=> $b['start']);
                for ($index = 0; $index < count($dayShifts) - 1; $index++) {
                    if ($dayShifts[$index]['end'] > $dayShifts[$index + 1]['start']) {
                        $validator->errors()->add('details', 'A slot overlaps another slot.');
                    }
                }
            }
        }];
    }
}
