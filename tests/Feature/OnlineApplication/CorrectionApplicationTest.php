<?php

namespace Tests\Feature\OnlineApplication;

use App\Models\Employee;
use App\Models\EmployeeLog;
use App\Models\Role;
use App\Models\User;
use App\Services\OnlineApplication\ApproverSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CorrectionApplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $applicant;

    private User $firstApprover;

    private User $secondApprover;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 14)->startOfDay());
        $this->applicant = $this->user();
        $this->firstApprover = $this->user();
        $this->secondApprover = $this->user();

        app(ApproverSetupService::class)->saveSetup([
            'employee_id' => $this->applicant->employee_id,
            'application_type' => 'correction',
            'approvers' => [
                ['approver_id' => $this->firstApprover->employee_id, 'sequence' => 1],
                ['approver_id' => $this->secondApprover->employee_id, 'sequence' => 2],
            ],
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

    public function test_employee_can_submit_correction_and_sync_logs_upon_final_approval(): void
    {
        $response = $this->postJson('/api/correction-applications', [
            'reason' => 'Missed PM timeout due to power outage.',
            'details' => [
                [
                    'date' => '2026-11-16',
                    'am_in' => '08:00',
                    'am_out' => null,
                    'pm_in' => null,
                    'pm_out' => '17:00',
                ],
            ],
        ])->assertCreated();

        $appId = $response->json('data.id');
        $response
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonCount(2, 'data.approvals');

        // Logs must not exist in employee_logs before final approval
        $this->assertDatabaseCount('employee_logs', 0);

        Sanctum::actingAs($this->firstApprover);
        $this->postJson("/api/correction-applications/{$appId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseCount('employee_logs', 0);

        Sanctum::actingAs($this->secondApprover);
        $this->postJson("/api/correction-applications/{$appId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('employee_logs', [
            'employee_id' => $this->applicant->employee_id,
            'logged_at' => '2026-11-16 08:00:00',
            'source' => 'correction',
            'device_id' => "CORRECTION-APP-{$appId}",
        ]);
        $this->assertDatabaseHas('employee_logs', [
            'employee_id' => $this->applicant->employee_id,
            'logged_at' => '2026-11-16 17:00:00',
            'source' => 'correction',
            'device_id' => "CORRECTION-APP-{$appId}",
        ]);
    }

    public function test_correction_validation_rejects_empty_slots_bad_order_and_duplicate_dates(): void
    {
        // Empty AM/PM slots
        $this->postJson('/api/correction-applications', [
            'reason' => 'No times entered.',
            'details' => [
                ['date' => '2026-11-16', 'am_in' => null, 'am_out' => null, 'pm_in' => null, 'pm_out' => null],
            ],
        ])->assertUnprocessable();

        // AM Out earlier than AM In
        $this->postJson('/api/correction-applications', [
            'reason' => 'Bad AM order.',
            'details' => [
                ['date' => '2026-11-16', 'am_in' => '10:00', 'am_out' => '09:00', 'pm_in' => null, 'pm_out' => null],
            ],
        ])->assertUnprocessable();

        // PM earlier than AM
        $this->postJson('/api/correction-applications', [
            'reason' => 'PM before AM.',
            'details' => [
                ['date' => '2026-11-16', 'am_in' => '08:00', 'am_out' => '12:00', 'pm_in' => '11:30', 'pm_out' => '17:00'],
            ],
        ])->assertUnprocessable();

        // Duplicate dates in same submission
        $this->postJson('/api/correction-applications', [
            'reason' => 'Duplicate date.',
            'details' => [
                ['date' => '2026-11-16', 'am_in' => '08:00', 'am_out' => '12:00', 'pm_in' => null, 'pm_out' => null],
                ['date' => '2026-11-16', 'am_in' => null, 'am_out' => null, 'pm_in' => '13:00', 'pm_out' => '17:00'],
            ],
        ])->assertUnprocessable();
    }

    public function test_admin_auto_approve_syncs_logs_immediately_and_override_disapprove_cleans_up(): void
    {
        $admin = $this->user('admin');
        $other = $this->user();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/correction-applications', [
            'employee_ids' => [$this->applicant->employee_id, $other->employee_id],
            'allow_approver' => false,
            'reason' => 'HR verified DTR log sheet.',
            'details' => [
                ['date' => '2026-11-18', 'am_in' => '08:00', 'am_out' => '12:00', 'pm_in' => '13:00', 'pm_out' => '17:00'],
            ],
        ])->assertCreated();

        $appId = $response->json('data.0.id');
        $this->assertEquals(4, EmployeeLog::where('device_id', "CORRECTION-APP-{$appId}")->count());

        $this->postJson("/api/correction-applications/{$appId}/disapprove", [
            'remarks' => 'Wrong date filed.',
        ])->assertOk()->assertJsonPath('data.status', 'disapproved');

        $this->assertEquals(0, EmployeeLog::where('device_id', "CORRECTION-APP-{$appId}")->count());
    }

    public function test_applicant_can_cancel_pending_correction(): void
    {
        $created = $this->postJson('/api/correction-applications', [
            'reason' => 'Entered wrong punch time.',
            'details' => [
                ['date' => '2026-11-19', 'am_in' => '08:00', 'am_out' => null, 'pm_in' => null, 'pm_out' => null],
            ],
        ])->assertCreated();

        $appId = $created->json('data.id');
        $this->postJson("/api/correction-applications/{$appId}/cancel", ['remarks' => 'Retracted.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }
}
