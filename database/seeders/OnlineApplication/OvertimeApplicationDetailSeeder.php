<?php

namespace Database\Seeders\OnlineApplication;

use App\Models\OnlineApplication\OvertimeApplication;
use App\Models\User;
use App\Services\OnlineApplication\OvertimeApplicationService;
use Illuminate\Database\Seeder;

class OvertimeApplicationDetailSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::whereHas('employee.schedules.schedule', fn ($query) => $query->where('name', 'Office - Mon to Fri'))
            ->whereHas('employee', fn ($query) => $query->where('type', '!=', 'admin')->orWhereNull('type'))
            ->orderBy('id')->first();
        if (! $actor || OvertimeApplication::where('employee_id', $actor->employee_id)->where('reason', 'Seeded overtime example.')->exists()) {
            return;
        }
        app(OvertimeApplicationService::class)->submit([
            'reason' => 'Seeded overtime example.',
            'details' => [[
                'date' => now('Asia/Manila')->toDateString(), 'start_time' => '18:00', 'end_time' => '21:00', 'break_seconds' => 1800,
            ]],
        ], $actor);
    }
}
