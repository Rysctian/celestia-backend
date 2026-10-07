<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FilterSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs($this->factory(User::class)->withRole(Role::where('code', 'admin')->firstOrFail())->create());
    }

    private function factory(string $model): Factory
    {
        return match ($model) {
            User::class => User::factory()->state(fn () => ['employee_id' => Employee::factory()->create()->employee_id]),
            Role::class => Role::factory()->state(fn () => ['code' => 'test_'.fake()->uuid()]),
            default => $model::factory(),
        };
    }

    public static function lists(): array
    {
        return [
            'schedules' => ['/api/schedules', Schedule::class],
            'users' => ['/api/users', User::class],
            'roles' => ['/api/roles', Role::class],
        ];
    }

    #[DataProvider('lists')]
    public function test_list_date_filters_sorting_and_offset(string $endpoint, string $model): void
    {
        $model::query()->update(['created_at' => '2026-09-01 00:00:00']);
        $this->factory($model)->create(['created_at' => '2026-09-30 23:59:59']);
        $first = $this->factory($model)->create(['created_at' => '2026-10-01 00:00:00']);
        $second = $this->factory($model)->create(['created_at' => '2026-10-31 23:59:59']);
        $this->factory($model)->create(['created_at' => '2026-11-01 00:00:00']);

        $query = ['date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'sort_by' => 'id', 'sort_order' => 'asc'];
        $this->getJson($endpoint.'?'.http_build_query($query))->assertOk()
            ->assertJsonPath('message', 'success')->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->id)->assertJsonPath('data.1.id', $second->id);

        $this->getJson($endpoint.'?'.http_build_query([...$query, 'limit' => 1, 'offset' => 1]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second->id);

        $this->getJson($endpoint.'?'.http_build_query([...$query, 'sort_by' => 'invalid', 'sort_order' => 'invalid']))
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $second->id);
    }

    #[DataProvider('lists')]
    public function test_list_limits_and_negative_offset(string $endpoint, string $model): void
    {
        $this->factory($model)->count(101)->create();

        $this->getJson($endpoint)->assertOk()->assertJsonCount(15, 'data');
        $this->getJson($endpoint.'?limit=999')->assertOk()->assertJsonCount(100, 'data');
        $firstId = $model::orderBy('id')->value('id');
        $this->getJson($endpoint.'?limit=0&offset=-5&sort_by=id&sort_order=asc')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $firstId);
    }

    public function test_schedule_search_and_filters_include_inactive_templates_and_details(): void
    {
        $schedule = Schedule::factory()->withWeek()->create([
            'name' => 'Office Archived', 'timezone' => 'Asia/Singapore', 'is_active' => false,
        ]);
        Schedule::factory()->create(['name' => 'Office Active', 'timezone' => 'Asia/Singapore']);
        Schedule::factory()->create(['name' => 'Office Other', 'timezone' => 'UTC', 'is_active' => false]);

        foreach (['Office', 'Singapore'] as $search) {
            foreach (['0', 'false'] as $inactive) {
                $query = ['search' => $search, 'timezone' => 'Asia/Singapore', 'is_active' => $inactive];
                $this->getJson('/api/schedules?'.http_build_query($query))->assertOk()
                    ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $schedule->id)
                    ->assertJsonPath('data.0.is_active', false)->assertJsonCount(12, 'data.0.details');
            }
        }

        $this->getJson('/api/schedules?name=Office%20Archived')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $schedule->id);
        $this->getJson('/api/schedules?is_active=1')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_active', true);
        $this->getJson('/api/schedules?is_active=')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_user_search_and_exact_filters_preserve_role_and_hide_credentials(): void
    {
        $role = Role::factory()->create();
        $employee = Employee::factory()->create(['employee_id' => 'EMP-SEARCH']);
        $user = User::factory()->forEmployee($employee)->withRole($role)->create([
            'name' => 'Search Person', 'email' => 'search@example.com', 'employee_id' => 'EMP-SEARCH',
        ]);
        $this->factory(User::class)->create(['name' => 'Search Wrong Role']);
        $this->factory(User::class)->withRole($role)->create(['name' => 'Unrelated Person', 'email' => 'other@example.com']);

        foreach (['Search Person', 'search@example.com', 'EMP-SEARCH'] as $search) {
            $query = ['search' => $search, 'role_id' => $role->id];
            $response = $this->getJson('/api/users?'.http_build_query($query))->assertOk()
                ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $user->id)
                ->assertJsonPath('data.0.role.id', $role->id);
            $this->assertArrayNotHasKey('password', $response->json('data.0'));
            $this->assertArrayNotHasKey('remember_token', $response->json('data.0'));
        }

        $this->getJson('/api/users?employee_id=EMP-SEARCH&email=search%40example.com')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $user->id);
        $this->getJson('/api/users?search=Search&email=other%40example.com')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_role_search_and_exact_filters_are_combined(): void
    {
        $role = Role::factory()->create(['name' => 'Schedule Manager', 'code' => 'scheduler']);
        Role::factory()->create(['name' => 'Schedule Assistant', 'code' => 'assistant']);

        foreach (['Schedule', 'scheduler'] as $search) {
            $query = ['search' => $search, 'code' => 'scheduler', 'name' => 'Schedule Manager'];
            $this->getJson('/api/roles?'.http_build_query($query))->assertOk()
                ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $role->id);
        }

        $this->getJson('/api/roles?search=Schedule&code=admin')->assertOk()->assertJsonCount(0, 'data');
    }
}
