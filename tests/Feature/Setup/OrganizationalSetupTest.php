<?php

namespace Tests\Feature\Setup;

use App\Models\Employee;
use App\Models\Role;
use App\Models\Setup\Office;
use App\Models\User;
use Database\Seeders\Setup\OrganizationalSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationalSetupTest extends TestCase
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

    public function test_organizational_setup_permissions_and_full_hierarchy_crud(): void
    {
        $this->actingAsRole('employee');
        $this->getJson('/api/setup/offices')->assertForbidden();
        $this->getJson('/api/setup/departments')->assertForbidden();
        $this->getJson('/api/setup/organizational/tree')->assertForbidden();

        $admin = $this->actingAsRole('admin');

        // 1. Office / College CRUD
        $officeId = $this->postJson('/api/setup/offices', [
            'code' => 'ccs',
            'name' => 'College of Computing Studies',
            'short_name' => 'CCS',
            'type' => 'college',
            'head_employee_id' => $admin->employee_id,
        ])->assertCreated()
            ->assertJsonPath('data.code', 'CCS')
            ->assertJsonPath('data.type', 'college')
            ->json('data.id');

        $subOfficeId = $this->postJson('/api/setup/offices', [
            'code' => 'CCS-LAB',
            'name' => 'Computing Laboratories Office',
            'type' => 'office',
            'parent_id' => $officeId,
        ])->assertCreated()->json('data.id');

        // Circular office parent must be rejected.
        $this->putJson("/api/setup/offices/{$officeId}", [
            'parent_id' => $subOfficeId,
        ])->assertUnprocessable()->assertJsonValidationErrors('parent_id');

        // 2. Department CRUD
        $deptId = $this->postJson('/api/setup/departments', [
            'code' => 'CS-DEPT',
            'name' => 'Computer Science Department',
            'office_id' => $officeId,
            'head_employee_id' => $admin->employee_id,
        ])->assertCreated()
            ->assertJsonPath('data.office.code', 'CCS')
            ->json('data.id');

        // 3. Division CRUD
        $divId = $this->postJson('/api/setup/divisions', [
            'code' => 'CS-SYS',
            'name' => 'Systems Division',
            'department_id' => $deptId,
        ])->assertCreated()
            ->assertJsonPath('data.department.code', 'CS-DEPT')
            ->json('data.id');

        // 4. Section / Unit CRUD (auto-inherits department from division when omitted)
        $sectionId = $this->postJson('/api/setup/sections', [
            'code' => 'SEC-AI',
            'name' => 'AI Research Unit',
            'division_id' => $divId,
        ])->assertCreated()
            ->assertJsonPath('data.department_id', $deptId)
            ->json('data.id');

        // 5. Position CRUD + cycle check
        $leadPosId = $this->postJson('/api/setup/positions', [
            'code' => 'POS-LEAD',
            'title' => 'Research Lead',
            'department_id' => $deptId,
            'division_id' => $divId,
            'section_id' => $sectionId,
            'salary_grade' => 'SG-20',
            'job_level' => 'Managerial',
        ])->assertCreated()->json('data.id');

        $devPosId = $this->postJson('/api/setup/positions', [
            'code' => 'POS-DEV',
            'title' => 'Research Engineer',
            'department_id' => $deptId,
            'division_id' => $divId,
            'section_id' => $sectionId,
            'reports_to_position_id' => $leadPosId,
            'salary_grade' => 'SG-16',
        ])->assertCreated()
            ->assertJsonPath('data.reports_to_position.code', 'POS-LEAD')
            ->json('data.id');

        $this->putJson("/api/setup/positions/{$leadPosId}", [
            'reports_to_position_id' => $devPosId,
        ])->assertUnprocessable()->assertJsonValidationErrors('reports_to_position_id');

        // 6. Reports Item CRUD + self-report guard
        $this->postJson('/api/setup/reports-items', [
            'code' => 'RPT-INVALID',
            'name' => 'Invalid Self Report',
            'position_id' => $devPosId,
            'reports_to_position_id' => $devPosId,
        ])->assertUnprocessable()->assertJsonValidationErrors('reports_to_position_id');

        $reportsItemId = $this->postJson('/api/setup/reports-items', [
            'code' => 'RPT-CS-01',
            'name' => 'Research Engineer Reporting Line',
            'office_id' => $officeId,
            'department_id' => $deptId,
            'division_id' => $divId,
            'section_id' => $sectionId,
            'position_id' => $devPosId,
            'reports_to_position_id' => $leadPosId,
            'reports_to_employee_id' => $admin->employee_id,
        ])->assertCreated()
            ->assertJsonPath('data.position.code', 'POS-DEV')
            ->assertJsonPath('data.reports_to_position.code', 'POS-LEAD')
            ->json('data.id');

        // Hierarchy tree returns nested structure.
        $this->getJson('/api/setup/organizational/tree')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'CCS')
            ->assertJsonPath('data.0.children.0.code', 'CCS-LAB')
            ->assertJsonPath('data.0.departments.0.code', 'CS-DEPT')
            ->assertJsonPath('data.0.departments.0.divisions.0.code', 'CS-SYS')
            ->assertJsonPath('data.0.departments.0.divisions.0.sections.0.code', 'SEC-AI');

        // Delete guards: cannot delete parent records while children exist.
        $this->deleteJson("/api/setup/offices/{$officeId}")->assertUnprocessable();
        $this->deleteJson("/api/setup/departments/{$deptId}")->assertUnprocessable();
        $this->deleteJson("/api/setup/divisions/{$divId}")->assertUnprocessable();
        $this->deleteJson("/api/setup/sections/{$sectionId}")->assertUnprocessable();
        $this->deleteJson("/api/setup/positions/{$leadPosId}")->assertUnprocessable();

        // Bottom-up deletion succeeds cleanly.
        $this->deleteJson("/api/setup/reports-items/{$reportsItemId}")->assertOk();
        $this->deleteJson("/api/setup/positions/{$devPosId}")->assertOk();
        $this->deleteJson("/api/setup/positions/{$leadPosId}")->assertOk();
        $this->deleteJson("/api/setup/sections/{$sectionId}")->assertOk();
        $this->deleteJson("/api/setup/divisions/{$divId}")->assertOk();
        $this->deleteJson("/api/setup/departments/{$deptId}")->assertOk();
        $this->deleteJson("/api/setup/offices/{$subOfficeId}")->assertOk();
        $this->deleteJson("/api/setup/offices/{$officeId}")->assertOk();

        $this->assertDatabaseCount('offices', 0);
    }

    public function test_organizational_seeder_is_idempotent(): void
    {
        $this->seed(OrganizationalSetupSeeder::class);
        $countFirst = Office::count();

        $this->seed(OrganizationalSetupSeeder::class);
        $this->assertSame($countFirst, Office::count());
        $this->assertGreaterThan(0, $countFirst);
    }
}
