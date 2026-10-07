<?php

namespace Database\Seeders;

use App\Models\EmployeeAttendance;
use App\Models\EmployeeLog;
use App\Services\Attendance\AttendanceCutoffService;
use Illuminate\Database\Seeder;

class AttendanceCutoffSummarySeeder extends Seeder
{
    public function run(): void
    {
        $cutoffs = app(AttendanceCutoffService::class);
        $employees = EmployeeLog::where('source', 'demo')->distinct()->pluck('employee_id');
        foreach (EmployeeAttendance::whereIn('employee_id', $employees)->orderBy('date')->get()->groupBy('employee_id') as $employeeId => $rows) {
            $cutoffs->build([
                'employee_id' => $employeeId,
                'date_from' => $rows->first()->date->toDateString(), 'date_to' => $rows->last()->date->toDateString(),
            ]);
        }
    }
}
