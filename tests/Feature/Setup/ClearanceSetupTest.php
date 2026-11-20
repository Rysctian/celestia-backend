<?php

namespace Tests\Feature\Setup;

use App\Models\Employee;
use App\Models\Role;
use App\Models\Setup\ClearanceType;
use App\Models\User;
use Database\Seeders\Setup\ClearanceSetupSeeder;
use Database\Seeders\Setup\OrganizationalSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClearanceSetupTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $roleCode = 'admin'): User
    {
        $employee = Employee::factory()->create();
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->forEmployee($employee)->create(['role_id' => $role->id]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_clearance_setup_permissions_crud_and_integrity_rules(): void
    {
        $this->actingAsRole('employee');
        $this->getJson('/api/setup/clearance-types')->assertForbidden();
        $this->getJson('/api/setup/clearance-requirements')->assertForbidden();
        $this->getJson('/api/setup/clearance-signatories')->assertForbidden();

        $admin = $this->actingAsRole('admin');

        $exitTypeId = $this->postJson('/api/setup/clearance-types', [
            'code' => 'clr-exit',
            'name' => 'Exit Clearance',
            'applies_to' => 'separation',
        ])->assertCreated()
            ->assertJsonPath('data.code', 'CLR-EXIT')
            ->json('data.id');

        $transferTypeId = $this->postJson('/api/setup/clearance-types', [
            'code' => 'CLR-TRANS',
            'name' => 'Transfer Clearance',
            'applies_to' => 'transfer',
        ])->assertCreated()->json('data.id');

        $reqId = $this->postJson('/api/setup/clearance-requirements', [
            'clearance_type_id' => $exitTypeId,
            'code' => 'REQ-IT',
            'name' => 'Laptop & Badge Return',
            'is_mandatory' => true,
            'requires_attachment' => true,
        ])->assertCreated()
            ->assertJsonPath('data.clearance_type.code', 'CLR-EXIT')
            ->json('data.id');

        // Signatory referencing a requirement from a different clearance type must fail.
        $this->postJson('/api/setup/clearance-signatories', [
            'clearance_type_id' => $transferTypeId,
            'clearance_requirement_id' => $reqId,
            'signatory_label' => 'Mismatched Signatory',
            'sign_order' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('clearance_requirement_id');

        $sigId = $this->postJson('/api/setup/clearance-signatories', [
            'clearance_type_id' => $exitTypeId,
            'clearance_requirement_id' => $reqId,
            'employee_id' => $admin->employee_id,
            'signatory_label' => 'IT Head',
            'sign_order' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.signatory_label', 'IT Head')
            ->json('data.id');

        // Viewing the clearance type includes nested requirements and signatories.
        $this->getJson("/api/setup/clearance-types/{$exitTypeId}")
            ->assertOk()
            ->assertJsonCount(1, 'data.requirements')
            ->assertJsonCount(1, 'data.signatories');

        // Deletion & move guards.
        $this->putJson("/api/setup/clearance-requirements/{$reqId}", [
            'clearance_type_id' => $transferTypeId,
        ])->assertUnprocessable()->assertJsonValidationErrors('clearance_type_id');

        $this->deleteJson("/api/setup/clearance-requirements/{$reqId}")->assertUnprocessable();
        $this->deleteJson("/api/setup/clearance-types/{$exitTypeId}")->assertUnprocessable();

        // Bottom-up cleanup succeeds.
        $this->deleteJson("/api/setup/clearance-signatories/{$sigId}")->assertOk();
        $this->deleteJson("/api/setup/clearance-requirements/{$reqId}")->assertOk();
        $this->deleteJson("/api/setup/clearance-types/{$exitTypeId}")->assertOk();
        $this->deleteJson("/api/setup/clearance-types/{$transferTypeId}")->assertOk();

        $this->assertDatabaseCount('clearance_types', 0);
    }

    public function test_clearance_seeder_is_idempotent(): void
    {
        $this->seed(OrganizationalSetupSeeder::class);
        $this->seed(ClearanceSetupSeeder::class);
        $firstCount = ClearanceType::count();

        $this->seed(ClearanceSetupSeeder::class);
        $this->assertSame($firstCount, ClearanceType::count());
        $this->assertSame(3, $firstCount);
    }
}
