<?php

namespace Database\Seeders\OnlineApplication;

use App\Models\OnlineApplication\LeavePolicy;
use Illuminate\Database\Seeder;

class LeavePolicySeeder extends Seeder
{
    public function run(): void
    {
        if (! LeavePolicy::where('name', 'Standard Leave Policy')->exists()) {
            LeavePolicy::create([
                'name' => 'Standard Leave Policy',
                'description' => 'Default company policy allowing half-day filings with no carry-over limit.',
                'min_notice_days' => 0,
                'allow_backdated_filing' => true,
                'max_backdated_days' => 30,
                'max_consecutive_days' => null,
                'allow_half_day' => true,
                'accrual_mode' => 'upfront_annual',
                'allow_negative_balance' => false,
                'carry_over_enabled' => false,
                'carry_over_max_days' => 0,
                'count_rest_days' => false,
                'allow_attachments' => true,
                'require_attachment' => false,
                'is_default' => ! LeavePolicy::where('is_default', true)->exists(),
                'is_active' => true,
            ]);
        }

        if (! LeavePolicy::where('name', 'Planned Vacation Policy')->exists()) {
            LeavePolicy::create([
                'name' => 'Planned Vacation Policy',
                'description' => 'Requires at least 3 days advance notice and disallows backdated vacation filings.',
                'min_notice_days' => 3,
                'allow_backdated_filing' => false,
                'max_backdated_days' => null,
                'max_consecutive_days' => 15,
                'allow_half_day' => true,
                'accrual_mode' => 'upfront_annual',
                'allow_negative_balance' => false,
                'carry_over_enabled' => true,
                'carry_over_max_days' => 5,
                'count_rest_days' => false,
                'allow_attachments' => false,
                'require_attachment' => false,
                'is_default' => false,
                'is_active' => true,
            ]);
        }
    }
}
