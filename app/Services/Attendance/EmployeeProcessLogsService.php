<?php

namespace App\Services\Attendance;

use App\Models\EmployeeLog;
use App\Models\EmployeeSchedule;
use Carbon\Carbon;

class EmployeeProcessLogsService
{
    public function __construct(
        private AttendanceCalculator $calculator
    ) {}

    public function process(string $employeeId, string $date): array
    {
        $day = Carbon::parse($date)->dayOfWeekIso;

        $employeeSchedule = EmployeeSchedule::with([
            'schedule.details' => fn ($query) => $query
                ->where('day_of_week', $day)
                ->orderBy('start_time'),
        ])->forDate($employeeId, $date)->first();

        $logs = EmployeeLog::forDate($employeeId, $date)->get();

        if (! $employeeSchedule) {
            return [
                'employee_id' => $employeeId,
                'date' => $date,
                'schedule_id' => null,
                'logs' => $logs,
                'result' => null,
                'remarks' => 'No schedule',
            ];
        }

        $details = $employeeSchedule->schedule->details;

        if ($details->isEmpty()) {
            return [
                'employee_id' => $employeeId,
                'date' => $date,
                'schedule_id' => $employeeSchedule->schedule_id,
                'logs' => $logs,
                'result' => null,
                'remarks' => 'No schedule details',
            ];
        }

        if ($details->every(fn ($detail) => $detail->is_rest_day)) {
            return [
                'employee_id' => $employeeId,
                'date' => $date,
                'schedule_id' => $employeeSchedule->schedule_id,
                'logs' => $logs,
                'result' => null,
                'remarks' => 'Rest day',
            ];
        }

        $result = $details->where('is_rest_day', false)->values()
            ->map(fn ($detail) => [
                'date' => $date,
                ...$this->calculator->calculate($date, collect([$detail]), $logs),
            ])->all();

        return [
            'employee_id' => $employeeId,
            'date' => $date,
            'schedule_id' => $employeeSchedule->schedule_id,
            'schedule' => $employeeSchedule->schedule,
            'logs' => $logs,
            'result' => $result,
        ];
    }
}
