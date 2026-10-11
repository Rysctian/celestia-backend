<?php

namespace Database\Seeders\OnlineApplication;

use App\Models\OnlineApplication\OvertimeApplication;
use App\Services\OnlineApplication\ApplicationApprovalService;
use Illuminate\Database\Seeder;

class ApplicationApprovalSeeder extends Seeder
{
    public function run(): void
    {
        // Never replace snapshots that already exist or alter terminal applications.
        $service = app(ApplicationApprovalService::class);
        foreach (OvertimeApplication::where('status', 'pending')->whereDoesntHave('approvals')->get() as $application) {
            $service->snapshot($application);
        }
    }
}
