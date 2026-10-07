<?php

namespace Database\Seeders;

use App\Models\EmployeeLog;
use App\Services\Attendance\AttendanceProcessor;
use Illuminate\Database\Seeder;

class TimesheetSeeder extends Seeder
{
    public function run(): void
    {
        $processor = app(AttendanceProcessor::class);
        $days = EmployeeLog::where('source', 'demo')->get()->groupBy(fn ($log) => $log->employee_id.':'.$this->workDate($log));
        foreach ($days as $logs) {
            $log = $logs->first();
            $processor->reprocessAttendance($log->employee_id, $this->workDate($log));
        }
    }

    private function workDate(EmployeeLog $log): string
    {
        return $log->raw_payload['attendance_date'] ?? $log->logged_at->setTimezone(config('attendance.timezone'))->toDateString();
    }
}
