<?php

namespace Tests\Feature\Setup;

use App\Models\Employee;
use App\Models\Role;
use App\Models\Setup\SetupReferenceItem;
use App\Models\User;
use Database\Seeders\Setup\SetupReferenceItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SetupReferenceTest extends TestCase
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

    public function test_categories_catalog_permissions_and_counts(): void
    {
        $this->actingAsRole('employee');
        $this->getJson('/api/setup/employee-information/categories')->assertForbidden();
        $this->getJson('/api/setup/employment/categories')->assertForbidden();
        $this->getJson('/api/setup/administrative/categories')->assertForbidden();

        $this->actingAsRole('admin');
        $this->seed(SetupReferenceItemSeeder::class);

        $this->getJson('/api/setup/employee-information/categories')
            ->assertOk()
            ->assertJsonCount(12, 'data');

        $this->getJson('/api/setup/employment/categories')
            ->assertOk()
            ->assertJsonCount(8, 'data');

        $this->getJson('/api/setup/administrative/categories')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_reference_item_crud_group_boundary_defaults_and_parent_linking(): void
    {
        $this->actingAsRole('admin');

        // Reject creating an employment category under employee-information endpoint.
        $this->postJson('/api/setup/employee-information/items', [
            'category' => 'employment_type',
            'code' => 'REG',
            'name' => 'Regular',
        ])->assertUnprocessable()->assertJsonValidationErrors('category');

        // Create parent separation_type and linked separation_reason under employment setup.
        $resignId = $this->postJson('/api/setup/employment/items', [
            'category' => 'separation_type',
            'code' => 'resign',
            'name' => 'Resignation',
            'is_default' => true,
        ])->assertCreated()
            ->assertJsonPath('data.code', 'RESIGN')
            ->assertJsonPath('data.group', 'employment')
            ->assertJsonPath('data.is_default', true)
            ->json('data.id');

        // Promoting a second default demotes the previous default in the same category.
        $retireId = $this->postJson('/api/setup/employment/items', [
            'category' => 'separation_type',
            'code' => 'RETIRE',
            'name' => 'Retirement',
            'is_default' => true,
        ])->assertCreated()->json('data.id');

        $this->assertFalse(SetupReferenceItem::findOrFail($resignId)->is_default);
        $this->assertTrue(SetupReferenceItem::findOrFail($retireId)->is_default);

        // Link separation_reason to parent separation_type.
        $reasonId = $this->postJson('/api/setup/employment/items', [
            'category' => 'separation_reason',
            'code' => 'CAREER',
            'name' => 'Career Growth',
            'parent_id' => $resignId,
            'metadata' => ['voluntary' => true],
        ])->assertCreated()
            ->assertJsonPath('data.parent.code', 'RESIGN')
            ->assertJsonPath('data.metadata.voluntary', true)
            ->json('data.id');

        // Parent linking rejected on categories that do not support parents.
        $this->postJson('/api/setup/employment/items', [
            'category' => 'work_arrangement',
            'code' => 'HYBRID',
            'name' => 'Hybrid',
            'parent_id' => $resignId,
        ])->assertUnprocessable()->assertJsonValidationErrors('parent_id');

        // Cannot delete parent while child exists.
        $this->deleteJson("/api/setup/employment/items/{$resignId}")->assertUnprocessable();

        // Update & delete child, then parent.
        $this->putJson("/api/setup/employment/items/{$reasonId}", [
            'name' => 'Better Career Opportunity',
        ])->assertOk()->assertJsonPath('data.name', 'Better Career Opportunity');

        $this->deleteJson("/api/setup/employment/items/{$reasonId}")->assertOk();
        $this->deleteJson("/api/setup/employment/items/{$resignId}")->assertOk();
    }

    public function test_options_endpoint_is_readable_by_regular_employees_and_filters_inactive(): void
    {
        $this->seed(SetupReferenceItemSeeder::class);

        SetupReferenceItem::where('category', 'gender')
            ->where('code', 'PREFER_NOT')
            ->update(['is_active' => false]);

        $this->actingAsRole('employee');

        $response = $this->getJson('/api/setup/options?categories=gender,civil_status')
            ->assertOk();

        $genderCodes = array_column($response->json('data.gender'), 'code');
        $this->assertContains('MALE', $genderCodes);
        $this->assertNotContains('PREFER_NOT', $genderCodes);
        $this->assertNotEmpty($response->json('data.civil_status'));
    }

    public function test_reference_seeder_covers_all_25_categories_and_is_idempotent(): void
    {
        $this->seed(SetupReferenceItemSeeder::class);
        $firstCount = SetupReferenceItem::count();

        $seededCategories = SetupReferenceItem::query()->distinct()->pluck('category')->all();
        $this->assertCount(25, $seededCategories);

        $this->seed(SetupReferenceItemSeeder::class);
        $this->assertSame($firstCount, SetupReferenceItem::count());
    }
}
