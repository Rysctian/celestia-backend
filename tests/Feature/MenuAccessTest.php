<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\MenuAccessSeeder;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MenuAccessTest extends TestCase
{
    use DatabaseMigrations;

    private function user(string $role = 'employee'): User
    {
        return User::factory()->forEmployee(Employee::factory()->create())
            ->withRole(Role::where('code', $role)->firstOrFail())->create();
    }

    public function test_fresh_migrations_seed_roles_and_new_users_default_to_employee(): void
    {
        $this->assertDatabaseHas('roles', ['id' => 1, 'code' => 'admin']);
        $this->assertDatabaseHas('roles', ['id' => 2, 'code' => 'employee']);

        $adminEmployee = Employee::factory()->create(['type' => 'admin']);
        $admin = User::factory()->forEmployee($adminEmployee)
            ->withRole(Role::where('code', 'admin')->firstOrFail())->create();
        $employee = $this->user();

        $this->assertSame('admin', $admin->fresh()->role->code);
        $this->assertSame('employee', $employee->fresh()->role->code);
        $new = User::factory()->forEmployee(Employee::factory()->create())->create();
        $this->assertSame('employee', $new->fresh()->role->code);
    }

    public function test_menu_tree_matches_grants_and_hides_empty_parents(): void
    {
        $employee = $this->user();
        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/me/menus')->assertOk();
        $this->assertSame(['dashboard', 'employee_database'], array_column($response->json('data'), 'code'));
        $response->assertJsonPath('data.1.children.0.code', 'employee_201')
            ->assertJsonPath('data.1.children.0.actions.view', true)
            ->assertJsonPath('data.1.children.0.actions.create', false);
        $this->getJson('/api/employees')->assertOk();
        $this->getJson('/api/schedules')->assertForbidden();
        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_admin_can_manage_roles_grants_and_user_assignments(): void
    {
        $admin = $this->user('admin');
        $employee = $this->user();
        Sanctum::actingAs($admin);

        $this->getJson('/api/menus')->assertOk()->assertJsonPath('data.1.children.0.code', 'schedule_list');
        $this->getJson('/api/users')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/users/{$employee->id}")->assertOk()->assertJsonPath('data.role.code', 'employee');

        $roleId = $this->postJson('/api/roles', ['code' => 'scheduler', 'name' => 'Scheduler'])
            ->assertCreated()->json('data.id');
        $this->putJson("/api/roles/$roleId", ['name' => 'Schedule Manager'])
            ->assertOk()->assertJsonPath('data.name', 'Schedule Manager');

        $scheduleId = Menu::where('code', 'schedule_list')->firstOrFail()->id;
        $this->putJson("/api/roles/$roleId/access", ['access' => [[
            'menu_id' => $scheduleId, 'can_view' => true, 'can_create' => true,
            'can_update' => false, 'can_delete' => false,
        ]]])->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/roles/$roleId/access")->assertJsonPath('data.0.can_create', true);
        $this->putJson("/api/users/{$employee->id}/role", ['role_id' => $roleId])
            ->assertOk()->assertJsonPath('data.role.code', 'scheduler');

        Sanctum::actingAs($employee->fresh());
        $this->getJson('/api/me/menus')->assertJsonPath('data.0.children.0.code', 'schedule_list');
        $this->getJson('/api/schedules')->assertOk();
        $this->getJson('/api/employees')->assertForbidden();
        $this->putJson('/api/schedules/1', [])->assertForbidden();
        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_grant_validation_and_replacement_deny_access_immediately(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $role = Role::factory()->create();
        $menuId = Menu::where('code', 'employee_201')->firstOrFail()->id;

        $this->putJson("/api/roles/{$role->id}/access", ['access' => [[
            'menu_id' => $menuId, 'can_view' => false, 'can_create' => true,
            'can_update' => false, 'can_delete' => false,
        ]]])->assertUnprocessable()->assertJsonValidationErrors('access.0.can_view');

        $parentId = Menu::where('code', 'employee_database')->firstOrFail()->id;
        $this->putJson("/api/roles/{$role->id}/access", ['access' => [[
            'menu_id' => $parentId, 'can_view' => true, 'can_create' => false,
            'can_update' => false, 'can_delete' => false,
        ]]])->assertUnprocessable();

        $user = User::factory()->forEmployee(Employee::factory()->create())->withRole($role)->create();
        $grant = ['menu_id' => $menuId, 'can_view' => true, 'can_create' => false,
            'can_update' => false, 'can_delete' => false];
        $this->putJson("/api/roles/{$role->id}/access", ['access' => [$grant]])->assertOk();
        Sanctum::actingAs($user);
        $this->getJson('/api/employees')->assertOk();

        Sanctum::actingAs($this->user('admin'));
        $this->putJson("/api/roles/{$role->id}/access", ['access' => []])->assertOk();
        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/employees')->assertForbidden();
    }

    public function test_employee_can_read_only_own_schedule_and_type_does_not_grant_admin_access(): void
    {
        $user = $this->user();
        $user->employee->update(['type' => 'admin']);
        $other = $this->user();
        Sanctum::actingAs($user);

        $this->getJson("/api/employees/{$user->employee_id}/schedules")->assertOk();
        $this->getJson("/api/employees/{$user->employee_id}/schedule?from=2026-10-01&to=2026-10-01")->assertOk();
        $this->getJson("/api/employees/{$other->employee_id}/schedules")->assertForbidden();
        $this->getJson("/api/employees/{$other->employee_id}/schedule?from=2026-10-01&to=2026-10-01")->assertForbidden();
        $this->postJson('/api/employees', [])->assertForbidden();
    }

    public function test_seeder_preserves_edited_admin_grants(): void
    {
        $admin = Role::where('code', 'admin')->firstOrFail();
        $menu = Menu::where('code', 'user_management')->firstOrFail();
        $admin->menus()->updateExistingPivot($menu->id, ['can_update' => false]);

        $this->seed(MenuAccessSeeder::class);

        $this->assertFalse((bool) $admin->menus()->where('menus.id', $menu->id)->firstOrFail()->pivot->can_update);
        $this->assertDatabaseCount('menus', 8);
    }

    public function test_menu_seeder_does_not_seed_access(): void
    {
        $menu = Menu::where('code', 'user_management')->firstOrFail();
        $menu->delete();
        $grantsBefore = DB::table('role_menu_access')->count();

        $this->seed(MenuSeeder::class);

        $this->assertDatabaseHas('menus', ['code' => 'user_management']);
        $this->assertDatabaseCount('role_menu_access', $grantsBefore);
    }
}
