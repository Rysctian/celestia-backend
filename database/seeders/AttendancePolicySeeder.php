<?php

namespace Database\Seeders;

use App\Models\AttendancePolicy;
use Illuminate\Database\Seeder;

class AttendancePolicySeeder extends Seeder
{
    public function run(): void
    {
        if (! AttendancePolicy::where('name', 'Standard Office Policy')->exists()) {
            AttendancePolicy::create([
                'name' => 'Standard Office Policy',
                'description' => 'Default attendance policy with a 15-minute tardy grace period counted from schedule start.',
                'tardy_grace_seconds' => 900,
                'tardy_count_from' => AttendancePolicy::COUNT_FROM_SCHEDULE_START,
                'absent_after_late_seconds' => null,
                'undertime_grace_seconds' => 0,
                'early_in_window_seconds' => 3600,
                'late_out_window_seconds' => 7200,
                'duplicate_tap_window_seconds' => 300,
                'is_default' => ! AttendancePolicy::where('is_default', true)->exists(),
                'is_active' => true,
            ]);
        }
    }
}
