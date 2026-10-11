<?php

namespace Database\Seeders\OnlineApplication;

use App\Models\Employee;
use App\Models\OnlineApplication\ApproverSetup;
use App\Models\User;
use App\Services\OnlineApplication\ApproverSetupService;
use Illuminate\Database\Seeder;

class ApproverSetupSeeder extends Seeder
{
    public function run(): void
    {
        $approver = User::whereHas('role', fn ($query) => $query->where('code', 'admin'))->first();
        if (! $approver) {
            return;
        }
        $service = app(ApproverSetupService::class);
        foreach (Employee::where('employee_id', '!=', $approver->employee_id)->orderBy('id')->get() as $employee) {
            foreach (ApproverSetup::APPLICATION_TYPES as $type) {
                if (ApproverSetup::where('employee_id', $employee->employee_id)->where('application_type', $type)->exists()) {
                    continue;
                }
                $service->saveSetup([
                    'employee_id' => $employee->employee_id, 'application_type' => $type,
                    'approvers' => [['approver_id' => $approver->employee_id, 'sequence' => 1]],
                ]);
            }
        }
    }
}
