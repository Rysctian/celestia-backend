<?php

namespace Tests\Feature\OnlineApplication;

use App\Models\Employee;
use App\Models\OnlineApplication\OfficialBusinessApplication;
use App\Models\Role;
use App\Models\User;
use App\Services\OnlineApplication\ApproverSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfficialBusinessApplicationTest extends TestCase
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
            'application_type' => 'official_business',
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

    public function test_employee_can_submit_ob_and_wfh_applications_and_follow_approvals(): void
    {
        // WFH without destination succeeds
        $wfhResponse = $this->postJson('/api/official-business-applications', [
            'ob_type' => 'wfh',
            'reason' => 'Sprint feature implementation from home.',
            'details' => [
                ['date' => '2026-11-16', 'start_time' => '08:00', 'end_time' => '17:00'],
            ],
        ])->assertCreated();

        $wfhId = $wfhResponse->json('data.id');
        $wfhResponse
            ->assertJsonPath('data.ob_type', 'wfh')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonCount(2, 'data.approvals');

        // OB requires destination
        $this->postJson('/api/official-business-applications', [
            'ob_type' => 'ob',
            'reason' => 'Client visit.',
            'details' => [
                ['date' => '2026-11-17', 'start_time' => '09:00', 'end_time' => '17:00'],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors(['destination']);

        // Sequential approvals move status to approved
        Sanctum::actingAs($this->firstApprover);
        $this->postJson("/api/official-business-applications/{$wfhId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        Sanctum::actingAs($this->secondApprover);
        $this->postJson("/api/official-business-applications/{$wfhId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_overlap_detection_and_cancel_workflow(): void
    {
        $created = $this->postJson('/api/official-business-applications', [
            'ob_type' => 'ob',
            'destination' => 'Makati Office',
            'reason' => 'System deployment.',
            'details' => [
                ['date' => '2026-11-18', 'start_time' => '09:00', 'end_time' => '12:00'],
            ],
        ])->assertCreated();

        $appId = $created->json('data.id');

        // Overlapping submission is rejected
        $this->postJson('/api/official-business-applications', [
            'ob_type' => 'wfh',
            'reason' => 'Overlap test.',
            'details' => [
                ['date' => '2026-11-18', 'start_time' => '11:00', 'end_time' => '14:00'],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors(['details.0.start_time']);

        // Applicant can cancel pending OB
        $this->postJson("/api/official-business-applications/{$appId}/cancel", [
            'remarks' => 'Rescheduled.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_admin_can_auto_approve_for_multiple_employees_and_filter_by_ob_type_and_status(): void
    {
        $admin = $this->user('admin');
        $other = $this->user('employee');
        Sanctum::actingAs($admin);

        $this->postJson('/api/official-business-applications', [
            'employee_ids' => [$this->applicant->employee_id, $other->employee_id],
            'allow_approver' => false,
            'ob_type' => 'wfh',
            'reason' => 'Company-wide remote day.',
            'details' => [
                ['date' => '2026-11-20', 'start_time' => '08:00', 'end_time' => '17:00'],
            ],
        ])->assertCreated();

        $this->assertSame(2, OfficialBusinessApplication::where('ob_type', 'wfh')->where('status', 'approved')->count());

        $this->getJson('/api/official-business-applications?ob_type[]=wfh&status[]=approved')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
