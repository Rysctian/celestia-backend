<?php

namespace Database\Seeders;

use App\Models\EmployeeAttendance;
use App\Models\EmployeeAttendanceLog;
use Illuminate\Database\Seeder;

class EmployeeAttendanceLogSeeder extends Seeder
{
    public function run(): void
    {
        EmployeeAttendance::with(['employee.logs' => fn ($query) => $query->orderBy('logged_at')])
            ->get()
            ->each(function (EmployeeAttendance $attendance) {
                $nextSequence = $attendance->attendanceLogs()->max('sequence') + 1;

                $attendance->employee->logs
                    ->filter(fn ($log) => $log->logged_at->isSameDay($attendance->date))
                    ->values()
                    ->each(function ($log) use ($attendance, &$nextSequence) {
                        $attendanceLog = EmployeeAttendanceLog::firstOrCreate([
                            'employee_attendance_id' => $attendance->id,
                            'employee_log_id' => $log->id,
                        ], [
                            'sequence' => $nextSequence,
                            'log_type' => $nextSequence % 2 === 1 ? 'in' : 'out',
                        ]);

                        if ($attendanceLog->wasRecentlyCreated) {
                            $nextSequence++;
                        }
                    });
            });
    }
}
