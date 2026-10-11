<?php

namespace Tests\Feature\OnlineApplication;

use App\Models\Employee;
use App\Models\OnlineApplication\ApproverSetup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApproverSetupTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return [
            'employee_id' => Employee::factory()->create()->employee_id, 'application_type' => 'overtime',
            'approvers' => [
                ['approver_id' => Employee::factory()->create()->employee_id, 'sequence' => 2],
                ['approver_id' => Employee::factory()->create()->employee_id, 'sequence' => 1],
            ],
        ];
    }

    private function login(string $role = 'admin'): void
    {
        Sanctum::actingAs(User::factory()->forEmployee(Employee::factory()->create())
            ->withRole(Role::where('code', $role)->firstOrFail())->create());
    }

    public function test_setup_supports_every_application_type_and_replaces_only_its_own_template(): void
    {
        $this->login();
        $data = $this->payload();
        foreach (ApproverSetup::APPLICATION_TYPES as $type) {
            $data['application_type'] = $type;
            $this->putJson('/api/approver-setups', $data)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.sequence', 1);
        }
        $data['application_type'] = 'overtime';
        $data['approvers'] = [$data['approvers'][1]];
        $this->putJson('/api/approver-setups', $data)->assertOk()->assertJsonCount(1, 'data');
        $this->assertDatabaseCount('approver_setup', 7);
        $this->getJson('/api/approver-setups/'.$data['employee_id'].'/leave')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/approver-setups?application_type=overtime&limit=1')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_empty_duplicate_missing_and_nonconsecutive_sequences_are_rejected(): void
    {
        $this->login();
        $data = $this->payload();
        $this->putJson('/api/approver-setups', [...$data, 'approvers' => []])->assertUnprocessable();
        foreach ([[1, 1], [2, 3], [1, 3], [0, 1]] as [$first, $second]) {
            $data['approvers'][0]['sequence'] = $first;
            $data['approvers'][1]['sequence'] = $second;
            $this->putJson('/api/approver-setups', $data)->assertUnprocessable();
        }
        $data = $this->payload();
        $data['approvers'][0]['approver_id'] = 'missing';
        $this->putJson('/api/approver-setups', $data)->assertUnprocessable()->assertJsonValidationErrors('approvers.0.approver_id');
        $this->assertDatabaseCount('approver_setup', 0);
    }

    public function test_employees_cannot_change_setup_or_list_approval_templates(): void
    {
        $this->login('employee');
        $this->putJson('/api/approver-setups', $this->payload())->assertForbidden();
        $this->getJson('/api/approver-setups')->assertForbidden();
    }

    public function test_invalid_type_and_unknown_employee_are_reported(): void
    {
        $this->login();
        $this->putJson('/api/approver-setups', [...$this->payload(), 'application_type' => 'unknown'])->assertUnprocessable();
        $this->getJson('/api/approver-setups/missing/overtime')->assertNotFound();
        $this->getJson('/api/approver-setups/EMP-1/unknown')->assertNotFound();
    }

    public function test_online_application_routes_require_authentication(): void
    {
        $this->getJson('/api/approver-setups')->assertUnauthorized();
        $this->getJson('/api/overtime-applications')->assertUnauthorized();
        $this->postJson('/api/overtime-applications', [])->assertUnauthorized();
        $this->postJson('/api/overtime-applications/1/approve')->assertUnauthorized();
    }
}
