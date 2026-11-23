<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\Setup\Department;
use App\Models\Setup\Office;
use App\Models\Setup\Position;
use App\Models\User;
use Database\Seeders\Setup\OrganizationalSetupSeeder;
use Database\Seeders\Setup\SetupReferenceItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeProfileTest extends TestCase
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

    public function test_admin_can_create_find_filter_and_update_employee_with_setup_fields(): void
    {
        $this->seed([
            OrganizationalSetupSeeder::class,
            SetupReferenceItemSeeder::class,
        ]);

        $admin = $this->actingAsRole('admin');
        $office = Office::where('code', 'OADMIN')->firstOrFail();
        $dept = Department::where('code', 'HRD')->firstOrFail();
        $position = Position::where('code', 'POS-HRSPEC')->firstOrFail();

        $payload = [
            'type' => 'REGULAR',
            'office_id' => $office->id,
            'department_id' => $dept->id,
            'position_id' => $position->id,
            'reports_to_employee_id' => $admin->employee_id,
            'first_name' => 'Avery',
            'middle_name' => 'Santos',
            'last_name' => 'Tan',
            'name_extension' => 'Jr.',
            'birth_date' => '1995-05-14',
            'birth_place' => 'Manila',
            'gender' => 'Female',
            'civil_status' => 'Single',
            'blood_type' => 'O_POS',
            'country' => 'PH',
            'nationality' => 'FILIPINO',
            'citizenship' => 'PH_CITIZEN',
            'religion' => 'ROMAN_CATHOLIC',
            'educational_attainment' => 'BACHELORS',
            'degree_course' => 'BSPSYCH',
            'languages' => ['EN', 'FIL'],
            'disability_type' => 'NONE',
            'personal_email' => 'avery.tan@example.com',
            'mobile_no' => '+63 917 555 0101',
            'telephone_no' => '(02) 8555-0101',
            'current_address' => '123 Ayala Ave, Makati City',
            'emergency_contact_name' => 'Maria Tan',
            'emergency_contact_relationship' => 'PARENT',
            'emergency_contact_no' => '+63 917 555 0199',
            'employment_status' => 'ACTIVE',
            'employee_category' => 'ADMIN',
            'appointment_type' => 'ORIGINAL',
            'employment_classification' => 'RANK_FILE',
            'work_arrangement' => 'HYBRID',
            'work_email' => 'avery.tan@celestia.local',
            'date_hired' => '2026-01-15',
            'regularization_date' => '2026-07-15',
            'tin_no' => '123-456-789-000',
            'sss_no' => '34-1234567-8',
            'philhealth_no' => '12-345678901-2',
            'pagibig_no' => '1234-5678-9012',
        ];

        $created = $this->postJson('/api/employees', $payload)
            ->assertCreated()
            ->assertJsonPath('data.first_name', 'Avery')
            ->assertJsonPath('data.office.code', 'OADMIN')
            ->assertJsonPath('data.department.code', 'HRD')
            ->assertJsonPath('data.position.code', 'POS-HRSPEC')
            ->assertJsonPath('data.blood_type', 'O_POS')
            ->assertJsonPath('data.languages.0', 'EN')
            ->assertJsonPath('data.work_arrangement', 'HYBRID');

        $employeeId = $created->json('data.employee_id');
        $this->assertNotEmpty($employeeId);
        $this->assertDatabaseHas('users', [
            'employee_id' => $employeeId,
            'email' => 'avery.tan@example.com',
        ]);

        // Filter by setup fields & org IDs
        $this->getJson('/api/employees?'.http_build_query([
            'department_id' => $dept->id,
            'position_id' => $position->id,
            'employment_status' => ['ACTIVE'],
            'work_arrangement' => ['HYBRID'],
        ]))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee_id', $employeeId);

        // Find returns eager-loaded user role + org relations
        $this->getJson("/api/employees/{$employeeId}")
            ->assertOk()
            ->assertJsonPath('data.department.code', 'HRD')
            ->assertJsonPath('data.reports_to.employee_id', $admin->employee_id)
            ->assertJsonPath('data.user.role.code', 'employee');

        // Update profile fields & sync user account email/name cleanly
        $this->putJson("/api/employees/{$employeeId}", [
            'first_name' => 'Avery Grace',
            'work_arrangement' => 'REMOTE',
            'employment_classification' => 'SUPERVISORY',
            'languages' => ['EN', 'FIL', 'JA'],
        ])->assertOk()
            ->assertJsonPath('data.first_name', 'Avery Grace')
            ->assertJsonPath('data.work_arrangement', 'REMOTE')
            ->assertJsonPath('data.languages.2', 'JA');

        $this->assertDatabaseHas('users', [
            'employee_id' => $employeeId,
            'name' => 'Avery Grace Tan',
        ]);
    }
}
