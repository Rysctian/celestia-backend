<?php

namespace Database\Seeders;

use App\Models\EmployeeAttendance;
use App\Models\User;
use App\Services\Attendance\AttendanceReviewService;
use Illuminate\Database\Seeder;

class AttendanceCorrectionSeeder extends Seeder
{
    public function run(): void
    {
        $row = EmployeeAttendance::whereHas('employee.logs', fn ($query) => $query->where('source', 'demo'))
            ->whereNotNull('time_in')->whereNotNull('time_out')
            ->orderBy('employee_id')->orderBy('date')->orderBy('sched_start')->first();
        $admin = User::whereHas('role', fn ($query) => $query->where('code', 'admin'))->first();
        if (! $row || ! $admin || $row->corrections()->exists()) {
            return;
        }
        app(AttendanceReviewService::class)->correct($row->id, [
            'time_in' => $row->time_in->toIso8601String(), 'time_out' => $row->time_out->toIso8601String(),
            'reason' => 'Demo: HR verified the captured timestamps.',
        ], $admin->id);
    }
}
