<?php

namespace App\Services\Attendance;

use App\Helpers\ScheduleHelper;
use App\Models\EmployeeSchedule;
use App\Models\OvertimeApplication;
use Carbon\CarbonImmutable;

class AttendanceScheduleService
{
    public function forDate(string $employeeId, string $date): array
    {
        $assignment = EmployeeSchedule::with('schedule.details')->where('employee_id', $employeeId)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderByDesc('effective_from')->lockForUpdate()->first();

        $timezone = $assignment?->schedule->timezone ?? config('attendance.timezone');
        $blocks = $assignment ? ScheduleHelper::shiftsForDate($assignment->schedule, CarbonImmutable::parse($date)) : [];
        foreach ($blocks as $index => $block) {
            $blocks[$index]['key'] = 'schedule:'.$block['detail_id'];
            $blocks[$index]['overtime_application_id'] = null;
        }

        // On rest/unassigned days, an overtime application supplies schedule context.
        if (! $blocks) {
            $applications = OvertimeApplication::where('employee_id', $employeeId)->whereDate('overtime_date', $date)
                ->whereIn('status', ['pending', 'approved'])->orderBy('time_from')->orderBy('id')->lockForUpdate()->get();
            foreach ($applications as $application) {
                $start = CarbonImmutable::parse($date.' '.$application->time_from, $timezone);
                $end = CarbonImmutable::parse($date.' '.$application->time_to, $timezone);
                if ($end->lessThan($start)) {
                    $end = $end->addDay();
                }
                if ($end->lessThanOrEqualTo($start)) {
                    continue;
                }
                // Merge overlapping application windows into one expected overtime block.
                $last = count($blocks) - 1;
                if ($last >= 0 && $start->lessThanOrEqualTo($blocks[$last]['end'])) {
                    $blocks[$last]['end'] = $blocks[$last]['end']->max($end);

                    continue;
                }
                $blocks[] = [
                    'key' => 'overtime:'.$application->id, 'detail_id' => null, 'schedule_id' => null,
                    'overtime_application_id' => $application->id, 'date' => $date, 'timezone' => $timezone,
                    'start' => $start, 'end' => $end, 'unpaid_break_minutes' => 0,
                ];
            }
        }

        return [
            'status' => $assignment ? 'rest_day' : 'unassigned',
            'timezone' => $timezone, 'blocks' => $blocks,
        ];
    }

    public function nearbyBlocks(string $employeeId, string $date): array
    {
        $blocks = [];
        foreach ([-1, 0, 1] as $offset) {
            $day = CarbonImmutable::parse($date)->addDays($offset)->toDateString();
            $blocks = array_merge($blocks, $this->forDate($employeeId, $day)['blocks']);
        }

        return $blocks;
    }
}
