<?php

namespace Database\Seeders\OnlineApplication;

use App\Models\OnlineApplication\LeavePolicy;
use App\Models\OnlineApplication\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $standardPolicyId = LeavePolicy::where('name', 'Standard Leave Policy')->value('id');
        $vacationPolicyId = LeavePolicy::where('name', 'Planned Vacation Policy')->value('id') ?? $standardPolicyId;

        $types = [
            [
                'code' => 'VL',
                'name' => 'Vacation Leave',
                'description' => 'Paid annual vacation leave.',
                'leave_policy_id' => $vacationPolicyId,
                'is_paid' => true,
                'requires_credits' => true,
                'default_credits' => 15,
                'is_active' => true,
            ],
            [
                'code' => 'SL',
                'name' => 'Sick Leave',
                'description' => 'Paid medical or sick leave.',
                'leave_policy_id' => $standardPolicyId,
                'is_paid' => true,
                'requires_credits' => true,
                'default_credits' => 15,
                'is_active' => true,
            ],
            [
                'code' => 'EL',
                'name' => 'Emergency Leave',
                'description' => 'Paid emergency leave for urgent personal matters.',
                'leave_policy_id' => $standardPolicyId,
                'is_paid' => true,
                'requires_credits' => true,
                'default_credits' => 5,
                'is_active' => true,
            ],
            [
                'code' => 'LWOP',
                'name' => 'Leave Without Pay',
                'description' => 'Unpaid leave when credits are exhausted or ineligible.',
                'leave_policy_id' => $standardPolicyId,
                'is_paid' => false,
                'requires_credits' => false,
                'default_credits' => 0,
                'is_active' => true,
            ],
        ];

        foreach ($types as $type) {
            LeaveType::firstOrCreate(['code' => $type['code']], $type);
        }
    }
}
