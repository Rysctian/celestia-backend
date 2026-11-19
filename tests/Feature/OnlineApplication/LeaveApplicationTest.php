<?php

namespace Tests\Feature\OnlineApplication;

use App\Models\Employee;
use App\Models\OnlineApplication\EmployeeLeaveCredit;
use App\Models\OnlineApplication\LeavePolicy;
use App\Models\OnlineApplication\LeaveType;
use App\Models\Role;
use App\Models\User;
use App\Services\OnlineApplication\ApproverSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveApplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $applicant;

    private User $firstApprover;

    private User $secondApprover;

    private LeavePolicy $standardPolicy;

    private LeaveType $vlType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 19)->startOfDay());

        $this->admin = $this->user('admin');
        $this->applicant = $this->user();
        $this->firstApprover = $this->user();
        $this->secondApprover = $this->user();

        app(ApproverSetupService::class)->saveSetup([
            'employee_id' => $this->applicant->employee_id,
            'application_type' => 'leave',
            'approvers' => [
                ['approver_id' => $this->firstApprover->employee_id, 'sequence' => 1],
                ['approver_id' => $this->secondApprover->employee_id, 'sequence' => 2],
            ],
        ]);

        $this->standardPolicy = LeavePolicy::create([
            'name' => 'Standard Test Policy',
            'min_notice_days' => 2,
            'allow_backdated_filing' => false,
            'max_backdated_days' => null,
            'max_consecutive_days' => 5,
            'allow_half_day' => true,
            'accrual_mode' => 'upfront_annual',
            'allow_negative_balance' => false,
            'carry_over_enabled' => false,
            'carry_over_max_days' => 0,
            'count_rest_days' => false,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->vlType = LeaveType::create([
            'code' => 'VL',
            'name' => 'Vacation Leave',
            'leave_policy_id' => $this->standardPolicy->id,
            'is_paid' => true,
            'requires_credits' => true,
            'default_credits' => 15,
            'is_active' => true,
        ]);

        EmployeeLeaveCredit::create([
            'employee_id' => $this->applicant->employee_id,
            'leave_type_id' => $this->vlType->id,
            'year' => 2026,
            'allocated_credits' => 5,
            'used_credits' => 0,
            'pending_credits' => 0,
        ]);

        Sanctum::actingAs($this->applicant);
    }

    private function user(string $role = 'employee'): User
    {
        return User::factory()
            ->forEmployee(Employee::factory()->create())
            ->withRole(Role::where('code', $role)->firstOrFail())
            ->create();
    }

    public function test_leave_credit_lifecycle_across_submit_approve_and_override_disapprove(): void
    {
        $response = $this->postJson('/api/leave-applications', [
            'leave_type_id' => $this->vlType->id,
            'reason' => 'Family trip',
            'details' => [
                ['date' => '2026-11-23', 'duration_type' => 'whole_day'],
                ['date' => '2026-11-24', 'duration_type' => 'half_day_am'],
            ],
        ])->assertCreated();

        $appId = $response->json('data.id');
        $response->assertJsonPath('data.total_days', 1.5);

        $credit = EmployeeLeaveCredit::where('employee_id', $this->applicant->employee_id)->firstOrFail();
        $this->assertSame(1.5, $credit->pending_credits);
        $this->assertSame(0.0, $credit->used_credits);
        $this->assertSame(3.5, $credit->available_credits);

        // First approver approves
        Sanctum::actingAs($this->firstApprover);
        $this->postJson("/api/leave-applications/{$appId}/approve")->assertOk()->assertJsonPath('data.status', 'pending');

        // Second approver finalizes approval -> pending moves to used
        Sanctum::actingAs($this->secondApprover);
        $this->postJson("/api/leave-applications/{$appId}/approve")->assertOk()->assertJsonPath('data.status', 'approved');

        $credit->refresh();
        $this->assertSame(0.0, $credit->pending_credits);
        $this->assertSame(1.5, $credit->used_credits);
        $this->assertSame(3.5, $credit->available_credits);

        // Admin override disapprove restores used credits
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/leave-applications/{$appId}/disapprove", ['remarks' => 'Rescheduled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'disapproved');

        $credit->refresh();
        $this->assertSame(0.0, $credit->used_credits);
        $this->assertSame(5.0, $credit->available_credits);
    }

    public function test_policy_rules_half_day_pairing_and_insufficient_balance_validation(): void
    {
        // Backdated filing blocked by policy
        $this->postJson('/api/leave-applications', [
            'leave_type_id' => $this->vlType->id,
            'reason' => 'Past date',
            'details' => [['date' => '2026-11-18', 'duration_type' => 'whole_day']],
        ])->assertUnprocessable();

        // Notice days blocked (min 2 days from 2026-11-19 -> earliest 2026-11-21)
        $this->postJson('/api/leave-applications', [
            'leave_type_id' => $this->vlType->id,
            'reason' => 'Too soon',
            'details' => [['date' => '2026-11-20', 'duration_type' => 'whole_day']],
        ])->assertUnprocessable();

        // Insufficient credits (> 5 days)
        $this->postJson('/api/leave-applications', [
            'leave_type_id' => $this->vlType->id,
            'reason' => 'Too many days',
            'details' => [
                ['date' => '2026-11-23', 'duration_type' => 'whole_day'],
                ['date' => '2026-11-24', 'duration_type' => 'whole_day'],
                ['date' => '2026-11-25', 'duration_type' => 'whole_day'],
                ['date' => '2026-11-26', 'duration_type' => 'whole_day'],
                ['date' => '2026-11-27', 'duration_type' => 'whole_day'],
                ['date' => '2026-11-28', 'duration_type' => 'whole_day'],
            ],
        ])->assertUnprocessable();

        // Half-day AM + Half-day PM can pair on the same date, whereas duplicate AM conflicts
        $this->postJson('/api/leave-applications', [
            'leave_type_id' => $this->vlType->id,
            'reason' => 'Morning off',
            'details' => [['date' => '2026-11-25', 'duration_type' => 'half_day_am']],
        ])->assertCreated();

        $this->postJson('/api/leave-applications', [
            'leave_type_id' => $this->vlType->id,
            'reason' => 'Conflicting AM',
            'details' => [['date' => '2026-11-25', 'duration_type' => 'half_day_am']],
        ])->assertUnprocessable();

        $this->postJson('/api/leave-applications', [
            'leave_type_id' => $this->vlType->id,
            'reason' => 'Afternoon off',
            'details' => [['date' => '2026-11-25', 'duration_type' => 'half_day_pm']],
        ])->assertCreated();
    }

    public function test_admin_can_manage_policies_types_and_allocate_credits(): void
    {
        Sanctum::actingAs($this->admin);

        $policyRes = $this->postJson('/api/leave-policies', [
            'name' => 'No Half Day Policy',
            'min_notice_days' => 0,
            'allow_backdated_filing' => true,
            'max_backdated_days' => 7,
            'max_consecutive_days' => 3,
            'allow_half_day' => false,
            'accrual_mode' => 'upfront_annual',
            'allow_negative_balance' => false,
            'carry_over_enabled' => false,
            'carry_over_max_days' => 0,
            'count_rest_days' => false,
            'allow_attachments' => true,
            'require_attachment' => true,
            'is_default' => false,
            'is_active' => true,
        ])->assertCreated();

        $policyId = $policyRes->json('data.id');

        $typeRes = $this->postJson('/api/leave-types', [
            'code' => 'SPL',
            'name' => 'Special Leave',
            'leave_policy_id' => $policyId,
            'is_paid' => true,
            'requires_credits' => true,
            'default_credits' => 3,
            'is_active' => true,
        ])->assertCreated();

        // Deleting assigned policy returns 422
        $this->deleteJson("/api/leave-policies/{$policyId}")->assertUnprocessable();

        // Bulk allocate
        $this->postJson('/api/employee-leave-credits/allocate', [
            'year' => 2026,
            'leave_type_id' => $typeRes->json('data.id'),
            'employee_ids' => [$this->applicant->employee_id],
        ])->assertOk()->assertJsonPath('data.created_count', 1);

        // Required attachment enforced
        Sanctum::actingAs($this->applicant);
        $this->postJson('/api/leave-applications', [
            'leave_type_id' => $typeRes->json('data.id'),
            'reason' => 'Special leave without attachment',
            'details' => [['date' => '2026-11-26', 'duration_type' => 'whole_day']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['attachment']);
    }
}
