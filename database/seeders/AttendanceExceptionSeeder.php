<?php

namespace Database\Seeders;

use App\Models\EmployeeLog;
use App\Models\User;
use App\Services\Attendance\AttendanceReviewService;
use Illuminate\Database\Seeder;

class AttendanceExceptionSeeder extends Seeder
{
    public function run(): void
    {
        $log = EmployeeLog::where('source', 'demo')->orderBy('employee_id')->first();
        $admin = User::whereHas('role', fn ($query) => $query->where('code', 'admin'))->first();
        if (! $log || ! $admin) {
            return;
        }
        app(AttendanceReviewService::class)->approveException([
            'employee_id' => $log->employee_id,
            'date' => $log->raw_payload['attendance_date'] ?? $log->logged_at->setTimezone(config('attendance.timezone'))->toDateString(),
            'type' => 'official_business', 'reason' => 'Demo: approved official business.',
        ], $admin->id);
    }
}
