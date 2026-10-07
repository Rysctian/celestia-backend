<?php

namespace Database\Seeders;

use App\Helpers\DateHelper;
use App\Models\EmployeeSchedule;
use App\Services\Attendance\AttendanceScheduleService;
use App\Services\Attendance\EmployeeLogService;
use Illuminate\Database\Seeder;

class EmployeeLogSeeder extends Seeder
{
    public function run(): void
    {
        $schedules = app(AttendanceScheduleService::class);
        $logs = app(EmployeeLogService::class);
        foreach (EmployeeSchedule::with('schedule')->orderBy('employee_id')->get() as $assignment) {
            $from = $assignment->effective_from;
            $to = $assignment->effective_to?->min($from->addDays(6)) ?? $from->addDays(6);
            foreach (DateHelper::dateRange($from, $to) as $date) {
                $blocks = $schedules->forDate($assignment->employee_id, $date)['blocks'];
                if (! $blocks) {
                    continue;
                }
                foreach ($blocks as $index => $block) {
                    foreach (['in' => $block['start']->addSeconds($index === 0 ? 300 : 0), 'out' => $block['end']] as $type => $time) {
                        $logs->capture([
                            'employee_id' => $assignment->employee_id, 'logged_at' => $time->toIso8601String(),
                            'source' => 'demo', 'device_id' => 'demo-device',
                            'external_log_id' => $assignment->employee_id.':'.$date.':'.$block['key'].':'.$type,
                            'raw_payload' => ['demo' => true, 'attendance_date' => $date],
                        ]);
                    }
                }
                break;
            }
        }
    }
}
