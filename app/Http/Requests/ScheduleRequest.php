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
            'details.*' => ['required', 'array:day_of_week,is_rest_day,start_time,end_time,unpaid_break_minutes'],
            'details.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'details.*.is_rest_day' => ['required', 'boolean'],
            'details.*.start_time' => ['present', 'nullable', 'date_format:H:i'],
            'details.*.end_time' => ['present', 'nullable', 'date_format:H:i'],
            'details.*.unpaid_break_minutes' => ['sometimes', 'integer', 'min:0', 'max:1439'],
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
                    if ($detail['start_time'] !== null || $detail['end_time'] !== null || ($detail['unpaid_break_minutes'] ?? 0) > 0) {
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

                if ($end === $start) {
                    $validator->errors()->add("details.$index", 'Start and end times must differ.');
                }
                if ($end < $start) {
                    $end += 1440;
                }
                if (($detail['unpaid_break_minutes'] ?? 0) >= $end - $start) {
                    $validator->errors()->add("details.$index", 'Unpaid break must be shorter than the working slot.');
                }

                $shifts[] = ['start' => ($weekday - 1) * 1440 + $start, 'end' => ($weekday - 1) * 1440 + $end];
            }

            if (count($weekdays) !== 7) {
                $validator->errors()->add('details', 'Configure all seven weekdays.');
            }

            foreach ($this->input('details') as $index => $detail) {
                if (isset($restDays[$detail['day_of_week']]) && $detailCounts[$detail['day_of_week']] > 1) {
                    $validator->errors()->add("details.$index", 'A rest day cannot have another slot.');
                }
            }

            // Include next Monday so Sunday overnight slots are checked too.
            $nextWeek = array_map(fn ($shift) => ['start' => $shift['start'] + 10080, 'end' => $shift['end'] + 10080], $shifts);
            $shifts = array_merge($shifts, $nextWeek);
            usort($shifts, fn ($a, $b) => $a['start'] <=> $b['start']);
            for ($index = 0; $index < count($shifts) - 1; $index++) {
                if ($shifts[$index]['end'] > $shifts[$index + 1]['start']) {
                    $validator->errors()->add('details', 'A slot overlaps another slot.');
                }
            }
        }];
    }
}
