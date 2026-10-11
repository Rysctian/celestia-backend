<?php

namespace Tests\Feature\OnlineApplication;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Menu;
use App\Models\OnlineApplication\ApplicationApproval;
use App\Models\OnlineApplication\ApproverSetup;
use App\Models\OnlineApplication\OvertimeApplication;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\User;
use App\Services\OnlineApplication\ApplicationApprovalService;
use App\Services\OnlineApplication\ApproverSetupService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\OnlineApplication\ApplicationApprovalSeeder;
use Database\Seeders\OnlineApplication\ApproverSetupSeeder;
use Database\Seeders\OnlineApplication\OvertimeApplicationSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OvertimeApplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $applicant;

    private User $firstApprover;

    private User $secondApprover;

    private Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 12)->startOfDay());
        $this->applicant = $this->user();
        $this->firstApprover = $this->user();
        $this->secondApprover = $this->user();
        $this->schedule = Schedule::factory()->withWeek()->create();
        EmployeeSchedule::factory()->create([
            'employee_id' => $this->applicant->employee_id, 'schedule_id' => $this->schedule->id,
            'effective_from' => '2026-10-01', 'effective_to' => null,
        ]);
        $this->setupChain();
        Sanctum::actingAs($this->applicant);
    }

    private function user(string $role = 'employee'): User
    {
        return User::factory()->forEmployee(Employee::factory()->create())
            ->withRole(Role::where('code', $role)->firstOrFail())->create();
    }

    private function setupChain(): void
    {
        app(ApproverSetupService::class)->saveSetup([
            'employee_id' => $this->applicant->employee_id, 'application_type' => 'overtime',
            'approvers' => [
                ['approver_id' => $this->firstApprover->employee_id, 'sequence' => 1],
                ['approver_id' => $this->secondApprover->employee_id, 'sequence' => 2],
            ],
        ]);
    }

    private function payload(): array
    {
        return ['reason' => 'Finish month-end reports.', 'details' => [
            ['date' => '2026-10-12', 'start_time' => '17:00', 'end_time' => '21:00', 'break_seconds' => 1800],
            ['date' => '2026-10-12', 'start_time' => '21:00', 'end_time' => '22:00'],
            ['date' => '2026-10-13', 'start_time' => '06:00', 'end_time' => '08:00'],
        ]];
    }

    private function submit(): int
    {
        Sanctum::actingAs($this->applicant);

        return $this->postJson('/api/overtime-applications', $this->payload())->assertCreated()->json('data.id');
    }

    public function test_submission_saves_multiple_dates_and_pairs_and_calculates_requested_seconds(): void
    {
        $response = $this->postJson('/api/overtime-applications', $this->payload())->assertCreated()
            ->assertJsonPath('data.employee_id', $this->applicant->employee_id)
            ->assertJsonPath('data.status', 'pending')->assertJsonCount(3, 'data.details')
            ->assertJsonPath('data.details.0.requested_seconds', 12600)
            ->assertJsonPath('data.details.0.break_duration', '00:30')
            ->assertJsonPath('data.details.1.break_seconds', 0)
            ->assertJsonCount(2, 'data.approvals')->assertJsonPath('data.approvals.0.status', 'pending');
        $this->assertNotNull($response->json('data.submitted_at'));
        $this->assertNull($response->json('data.approvals.0.acted_at'));
    }

    public function test_missing_setup_rolls_back_the_application_and_details(): void
    {
        ApproverSetup::query()->delete();
        $this->postJson('/api/overtime-applications', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('approvers');
        $this->assertDatabaseCount('overtime_applications', 0);
        $this->assertDatabaseCount('overtime_application_details', 0);
        $this->assertDatabaseCount('application_approvals', 0);
    }

    public function test_setup_changes_do_not_modify_submitted_snapshots(): void
    {
        $id = $this->submit();
        app(ApproverSetupService::class)->saveSetup([
            'employee_id' => $this->applicant->employee_id, 'application_type' => 'overtime',
            'approvers' => [['approver_id' => $this->secondApprover->employee_id, 'sequence' => 1]],
        ]);
        $this->getJson("/api/overtime-applications/$id")->assertJsonCount(2, 'data.approvals')
            ->assertJsonPath('data.approvals.0.approver_id', $this->firstApprover->employee_id);
        $this->postJson('/api/overtime-applications', $this->payload())->assertCreated()->assertJsonCount(1, 'data.approvals')
            ->assertJsonPath('data.approvals.0.approver_id', $this->secondApprover->employee_id);
    }

    public function test_only_current_approver_can_act_and_all_approvals_finalize_parent(): void
    {
        $id = $this->submit();
        Sanctum::actingAs($this->secondApprover);
        $this->postJson("/api/overtime-applications/$id/approve")->assertForbidden();
        Sanctum::actingAs($this->firstApprover);
        $this->postJson("/api/overtime-applications/$id/approve", ['remarks' => 'Looks good.'])->assertOk()
            ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.approvals.0.status', 'approved')
            ->assertJsonPath('data.approvals.0.remarks', 'Looks good.');
        $this->postJson("/api/overtime-applications/$id/approve")->assertForbidden();
        Sanctum::actingAs($this->secondApprover);
        $this->postJson("/api/overtime-applications/$id/approve")->assertOk()->assertJsonPath('data.status', 'approved');
        $this->postJson("/api/overtime-applications/$id/approve")->assertUnprocessable();
        $this->assertSame(2, ApplicationApproval::whereNotNull('acted_at')->count());
    }

    public function test_another_account_of_the_same_approver_employee_can_act(): void
    {
        $id = $this->submit();
        $otherAccount = User::factory()->forEmployee($this->firstApprover->employee)->create();
        Sanctum::actingAs($otherAccount);
        $this->postJson("/api/overtime-applications/$id/approve")->assertOk()->assertJsonPath('data.approvals.0.status', 'approved');
    }

    public function test_disapproval_stops_the_chain(): void
    {
        $id = $this->submit();
        Sanctum::actingAs($this->firstApprover);
        $this->postJson("/api/overtime-applications/$id/disapprove", ['remarks' => 'Not needed.'])->assertOk()
            ->assertJsonPath('data.status', 'disapproved')->assertJsonPath('data.approvals.0.status', 'disapproved');
        Sanctum::actingAs($this->secondApprover);
        $this->postJson("/api/overtime-applications/$id/approve")->assertUnprocessable();
        $this->assertDatabaseHas('application_approvals', ['sequence' => 2, 'status' => 'pending', 'acted_at' => null]);
    }

    public function test_only_owner_can_cancel_and_previous_decisions_remain_intact(): void
    {
        $id = $this->submit();
        Sanctum::actingAs($this->firstApprover);
        $this->postJson("/api/overtime-applications/$id/cancel")->assertForbidden();
        $this->postJson("/api/overtime-applications/$id/approve")->assertOk();
        Sanctum::actingAs($this->applicant);
        $response = $this->postJson("/api/overtime-applications/$id/cancel", ['remarks' => 'Plans changed.'])->assertOk()
            ->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.approvals.0.status', 'approved')
            ->assertJsonPath('data.approvals.1.status', 'cancelled')->assertJsonPath('data.approvals.1.remarks', 'Plans changed.');
        $this->assertNotNull($response->json('data.approvals.1.acted_at'));
        Sanctum::actingAs($this->secondApprover);
        $this->postJson("/api/overtime-applications/$id/disapprove")->assertUnprocessable();
    }

    public function test_owner_cannot_cancel_a_finalized_application(): void
    {
        $id = $this->submit();
        Sanctum::actingAs($this->firstApprover);
        $this->postJson("/api/overtime-applications/$id/disapprove")->assertOk();
        Sanctum::actingAs($this->applicant);
        $this->postJson("/api/overtime-applications/$id/cancel")->assertUnprocessable();
    }

    public function test_schedule_overlap_and_detail_overlap_are_rejected_atomically(): void
    {
        $data = $this->payload();
        $data['details'][0]['start_time'] = '16:59';
        $this->postJson('/api/overtime-applications', $data)->assertUnprocessable()->assertJsonValidationErrors('details.0.start_time');
        $data = $this->payload();
        $data['details'][1]['start_time'] = '20:59';
        $this->postJson('/api/overtime-applications', $data)->assertUnprocessable()->assertJsonValidationErrors('details.1.start_time');
        $this->assertDatabaseCount('overtime_applications', 0);
    }

    public function test_rest_day_is_allowed_but_previous_overnight_work_still_blocks_overlap(): void
    {
        $data = $this->payload();
        $data['details'] = [['date' => '2026-10-17', 'start_time' => '08:00', 'end_time' => '12:00']];
        $this->postJson('/api/overtime-applications', $data)->assertCreated()->assertJsonPath('data.details.0.requested_seconds', 14400);
        $this->schedule->details()->where('day_of_week', 5)->delete();
        $this->schedule->details()->create(['day_of_week' => 5, 'is_rest_day' => false, 'start_time' => '22:00', 'end_time' => '06:00']);
        $data['details'][0]['start_time'] = '05:00';
        $data['details'][0]['end_time'] = '07:00';
        $this->postJson('/api/overtime-applications', $data)->assertUnprocessable()->assertJsonValidationErrors('details.0.start_time');
    }

    public function test_missing_schedule_is_not_treated_as_a_rest_day(): void
    {
        EmployeeSchedule::query()->delete();
        $this->postJson('/api/overtime-applications', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('details.0.date');
    }

    public function test_time_break_and_server_controlled_fields_are_validated(): void
    {
        foreach (['16:00', '17:00', '05:00'] as $end) {
            $data = $this->payload();
            $data['details'][0]['end_time'] = $end;
            $this->postJson('/api/overtime-applications', $data)->assertUnprocessable()->assertJsonValidationErrors('details.0.end_time');
        }
        foreach ([-1, 14400, 15000, '00:30'] as $break) {
            $data = $this->payload();
            $data['details'][0]['break_seconds'] = $break;
            $this->postJson('/api/overtime-applications', $data)->assertUnprocessable()->assertJsonValidationErrors('details.0.break_seconds');
        }
        $data = $this->payload();
        $data['details'][0]['requested_seconds'] = 999999;
        $this->postJson('/api/overtime-applications', $data)->assertUnprocessable()->assertJsonValidationErrors('details.0');
        $data = $this->payload();
        $data['status'] = 'approved';
        $this->postJson('/api/overtime-applications', $data)->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertDatabaseCount('overtime_applications', 0);
    }

    public function test_visibility_and_submission_are_limited_to_the_applicant_and_snapshot_approvers(): void
    {
        $id = $this->submit();
        Sanctum::actingAs($this->firstApprover);
        $this->getJson('/api/overtime-applications')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/overtime-applications/$id")->assertOk();
        Sanctum::actingAs($this->user('admin'));
        $this->getJson('/api/overtime-applications')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/overtime-applications/$id")->assertNotFound();
        $this->postJson("/api/overtime-applications/$id/approve")->assertForbidden();
        $data = $this->payload();
        $data['employee_id'] = $this->applicant->employee_id;
        $this->postJson('/api/overtime-applications', $data)->assertForbidden();
    }

    public function test_permissions_are_required_even_for_a_configured_approver(): void
    {
        $id = $this->submit();
        $menu = Menu::where('code', 'overtime_application')->firstOrFail();
        $this->firstApprover->role->menus()->updateExistingPivot($menu->id, ['can_update' => false]);
        Sanctum::actingAs($this->firstApprover);
        $this->postJson("/api/overtime-applications/$id/approve")->assertForbidden();
    }

    public function test_application_filtering_preserves_visibility_and_bounded_paging(): void
    {
        $id = $this->submit();
        OvertimeApplication::factory()->forEmployee($this->applicant->employee)->withDetails()->create(['status' => 'cancelled']);
        OvertimeApplication::factory()->withDetails()->create();
        $this->getJson('/api/overtime-applications?status=pending&limit=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/overtime-applications?search=month-end&sort_by=id&sort_order=asc')->assertJsonCount(1, 'data');
        $this->getJson('/api/overtime-applications?limit=1&offset=1')->assertJsonCount(1, 'data');
        $this->getJson('/api/overtime-applications?date_from=2026-10-13')->assertJsonCount(0, 'data');
    }

    public function test_factory_relations_and_new_schema_have_no_legacy_overtime_fields(): void
    {
        $approval = ApplicationApproval::factory()->create();
        $this->assertSame($approval->employee_id, $approval->application->employee_id);
        $this->assertSame(1, $approval->application->details()->count());
        $this->assertFalse(Schema::hasColumn('overtime_applications', 'overtime_date'));
        $this->assertFalse(Schema::hasColumn('overtime_applications', 'allow_approver'));
        $this->assertFalse(Schema::hasColumn('overtime_applications', 'approved_by'));
    }

    public function test_fresh_database_seeding_populates_new_tables_and_preserves_snapshots_when_reseeded(): void
    {
        $this->seed(DatabaseSeeder::class);
        $application = OvertimeApplication::where('reason', 'Seeded overtime example.')->firstOrFail();
        $this->assertSame(9000, $application->details->first()->requested_seconds);
        $this->assertSame(1, $application->approvals()->count());
        $snapshot = $application->approvals->first()->toArray();
        $count = ApproverSetup::count();
        $this->seed([ApproverSetupSeeder::class, OvertimeApplicationSeeder::class, ApplicationApprovalSeeder::class]);
        $this->assertSame($count, ApproverSetup::count());
        $this->assertSame($snapshot, $application->fresh()->approvals->first()->toArray());
        $this->assertDatabaseCount('overtime_application_details', 1);
    }

    public function test_existing_snapshots_cannot_be_replaced_or_duplicated(): void
    {
        $id = $this->submit();
        $application = OvertimeApplication::findOrFail($id);
        $snapshot = $application->approvals->toArray();
        try {
            app(ApplicationApprovalService::class)->snapshot($application);
            $this->fail('Expected duplicate snapshot to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approvals', $exception->errors());
        }
        $this->assertSame($snapshot, $application->fresh()->approvals->toArray());
    }

    public function test_parent_write_failure_rolls_back_the_approval_decision(): void
    {
        $id = $this->submit();
        Sanctum::actingAs($this->firstApprover);
        $this->postJson("/api/overtime-applications/$id/approve")->assertOk();
        Event::listen('eloquent.updating: '.OvertimeApplication::class, function () {
            throw new \RuntimeException('Simulated parent write failure.');
        });
        try {
            app(ApplicationApprovalService::class)->decide(OvertimeApplication::findOrFail($id), $this->secondApprover->employee_id, 'approved');
            $this->fail('Expected parent update failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated parent write failure.', $exception->getMessage());
        } finally {
            Event::forget('eloquent.updating: '.OvertimeApplication::class);
        }
        $this->assertDatabaseHas('application_approvals', ['application_id' => $id, 'sequence' => 2, 'status' => 'pending', 'acted_at' => null]);
        $this->assertSame('pending', OvertimeApplication::findOrFail($id)->status);
    }

    public function test_generic_approval_service_can_handle_a_future_application_without_overtime_logic(): void
    {
        Schema::create('test_leave_applications', function ($table) {
            $table->id();
            $table->string('employee_id');
            $table->string('status');
            $table->timestamps();
        });
        Relation::morphMap(['leave' => TestLeaveApplication::class]);
        app(ApproverSetupService::class)->saveSetup([
            'employee_id' => $this->applicant->employee_id, 'application_type' => 'leave',
            'approvers' => [['approver_id' => $this->firstApprover->employee_id, 'sequence' => 1]],
        ]);
        $overtimeId = $this->submit();
        $leave = TestLeaveApplication::create(['employee_id' => $this->applicant->employee_id, 'status' => 'pending']);
        $service = app(ApplicationApprovalService::class);
        $service->snapshot($leave);
        $service->decide($leave, $this->firstApprover->employee_id, 'approved');
        $this->assertSame('approved', $leave->fresh()->status);
        $this->assertSame('pending', OvertimeApplication::findOrFail($overtimeId)->status);
        Schema::dropIfExists('test_leave_applications');
    }
}

class TestLeaveApplication extends Model
{
    protected $table = 'test_leave_applications';

    protected $guarded = [];
}
