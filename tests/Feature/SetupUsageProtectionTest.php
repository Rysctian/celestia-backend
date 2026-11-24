<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\Setup\ClearanceRequirement;
use App\Models\Setup\ClearanceType;
use App\Models\Setup\Department;
use App\Models\Setup\Office;
use App\Models\Setup\Position;
use App\Models\Setup\SetupReferenceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SetupUsageProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $employee = Employee::factory()->create();
        $role = Role::where('code', 'admin')->firstOrFail();
        $user = User::factory()->forEmployee($employee)->create(['role_id' => $role->id]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_organizational_setup_flags_and_blocks_deleting_items_used_by_employees(): void
    {
        $this->actingAsAdmin();

        $usedOffice = Office::create(['code' => 'USED-OFF', 'name' => 'Used Office', 'type' => 'office', 'is_active' => true]);
        $unusedOffice = Office::create(['code' => 'FREE-OFF', 'name' => 'Free Office', 'type' => 'office', 'is_active' => true]);
        $usedDept = Department::create(['code' => 'USED-DEPT', 'name' => 'Used Department', 'is_active' => true]);
        $usedPos = Position::create(['code' => 'USED-POS', 'title' => 'Used Position', 'is_active' => true]);

        Employee::factory()->create([
            'office_id' => $usedOffice->id,
            'department_id' => $usedDept->id,
            'position_id' => $usedPos->id,
        ]);

        // Offices list flags usedOffice as in use and unusedOffice as not in use
        $officesRes = $this->getJson('/api/setup/offices')->assertOk();
        $offices = collect($officesRes->json('data'))->keyBy('code');

        $this->assertTrue($offices['USED-OFF']['is_in_use']);
        $this->assertSame(1, $offices['USED-OFF']['usage_count']);
        $this->assertStringContainsString('1 employee', $offices['USED-OFF']['usage_reason']);

        $this->assertFalse($offices['FREE-OFF']['is_in_use']);
        $this->assertSame(0, $offices['FREE-OFF']['usage_count']);

        // Deleting in-use office/department/position fails with 422
        $this->deleteJson("/api/setup/offices/{$usedOffice->id}")->assertStatus(422);
        $this->deleteJson("/api/setup/departments/{$usedDept->id}")->assertStatus(422);
        $this->deleteJson("/api/setup/positions/{$usedPos->id}")->assertStatus(422);

        // Deleting unused office succeeds
        $this->deleteJson("/api/setup/offices/{$unusedOffice->id}")->assertOk();
    }

    public function test_reference_setup_flags_and_blocks_deleting_scalar_and_language_items_used_by_employees(): void
    {
        $this->actingAsAdmin();

        $usedBlood = SetupReferenceItem::create([
            'group' => 'employee_info',
            'category' => 'blood_type',
            'code' => 'O_POS',
            'name' => 'O+',
            'is_active' => true,
        ]);
        $unusedBlood = SetupReferenceItem::create([
            'group' => 'employee_info',
            'category' => 'blood_type',
            'code' => 'AB_NEG',
            'name' => 'AB-',
            'is_active' => true,
        ]);
        $usedLang = SetupReferenceItem::create([
            'group' => 'employee_info',
            'category' => 'language',
            'code' => 'EN',
            'name' => 'English',
            'is_active' => true,
        ]);

        Employee::factory()->create([
            'blood_type' => 'O_POS',
            'languages' => ['EN'],
        ]);

        $bloodRes = $this->getJson('/api/setup/employee-information/items?category=blood_type')->assertOk();
        $bloodItems = collect($bloodRes->json('data'))->keyBy('code');
        $this->assertTrue($bloodItems['O_POS']['is_in_use']);
        $this->assertStringContainsString('1 employee', $bloodItems['O_POS']['usage_reason']);
        $this->assertFalse($bloodItems['AB_NEG']['is_in_use']);

        $langRes = $this->getJson('/api/setup/employee-information/items?category=language')->assertOk();
        $langItems = collect($langRes->json('data'))->keyBy('code');
        $this->assertTrue($langItems['EN']['is_in_use']);

        // Cannot delete used items
        $this->deleteJson("/api/setup/employee-information/items/{$usedBlood->id}")->assertStatus(422);
        $this->deleteJson("/api/setup/employee-information/items/{$usedLang->id}")->assertStatus(422);

        // Can delete unused item
        $this->deleteJson("/api/setup/employee-information/items/{$unusedBlood->id}")->assertOk();
    }

    public function test_clearance_setup_flags_and_blocks_deleting_types_in_use(): void
    {
        $this->actingAsAdmin();

        $type = ClearanceType::create([
            'code' => 'CLR-SEP',
            'name' => 'Separation Clearance',
            'applies_to' => 'separation',
            'is_active' => true,
        ]);

        ClearanceRequirement::create([
            'clearance_type_id' => $type->id,
            'code' => 'REQ-ID',
            'name' => 'Surrender Company ID',
            'is_mandatory' => true,
            'is_active' => true,
        ]);

        $res = $this->getJson('/api/setup/clearance-types')->assertOk();
        $items = collect($res->json('data'))->keyBy('code');

        $this->assertTrue($items['CLR-SEP']['is_in_use']);
        $this->assertSame(1, $items['CLR-SEP']['usage_count']);
        $this->assertStringContainsString('1 requirement', $items['CLR-SEP']['usage_reason']);

        $this->deleteJson("/api/setup/clearance-types/{$type->id}")->assertStatus(422);
    }
}
