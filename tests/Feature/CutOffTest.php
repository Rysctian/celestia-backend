<?php

namespace Tests\Feature;

use App\Models\CutOff;
use App\Models\CutOffPeriod;
use App\Models\Employee;
use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use App\Services\CutOffService;
use Database\Seeders\CutOffPeriodSeeder;
use Database\Seeders\CutOffSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CutOffTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $role = 'admin'): User
    {
        $user = User::factory()->forEmployee(Employee::factory()->create())
            ->withRole(Role::where('code', $role)->firstOrFail())->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function period(int $number, string $from, string $to): array
    {
        return [
            'cut_off_no' => $number,
            'no_dtr' => false, 'dtr_date_from' => $from, 'dtr_date_to' => $to,
            'confirmation_start_date' => '2026-11-01', 'confirmation_end_date' => '2026-11-02',
            'confirmation_time_from' => '08:00', 'confirmation_time_to' => '17:00',
            'payroll_date_from' => $from, 'payroll_date_to' => $to, 'release_date' => '2026-11-05',
        ];
    }

    private function payload(string $type = 'semi_monthly'): array
    {
        $periods = [$this->period(1, '2026-10-01', '2026-10-31')];
        if ($type === 'semi_monthly') {
            $periods = [$this->period(1, '2026-10-01', '2026-10-15'), $this->period(2, '2026-10-16', '2026-10-31')];
        }

        return ['schedule_type' => $type, 'name' => 'October 2026', 'year' => 2026, 'month' => 10, 'is_active' => true, 'periods' => $periods];
    }

    public function test_admin_can_manage_cut_offs_and_their_periods(): void
    {
        $this->login();
        $payload = $this->payload();
        $payload['periods'] = array_reverse($payload['periods']);
        $response = $this->postJson('/api/cut-offs', $payload)->assertCreated()
            ->assertJsonPath('data.schedule_type', 'semi_monthly')->assertJsonCount(2, 'data.periods')
            ->assertJsonPath('data.periods.0.cut_off_no', 1)->assertJsonPath('data.periods.1.cut_off_no', 2)
            ->assertJsonPath('data.periods.0.confirmation_time_from', '08:00:00');
        $id = $response->json('data.id');
        $this->getJson("/api/cut-offs/$id")->assertOk()->assertJsonPath('data.month', 10);
        $this->getJson('/api/cut-offs?schedule_type=semi_monthly')->assertOk()->assertJsonCount(1, 'data');

        $updated = $this->payload('monthly');
        $updated['is_active'] = false;
        $this->putJson("/api/cut-offs/$id", $updated)->assertOk()
            ->assertJsonPath('data.schedule_type', 'monthly')->assertJsonPath('data.is_active', false)->assertJsonCount(1, 'data.periods');
        $this->assertDatabaseCount('cut_off_periods', 1);
        $this->deleteJson("/api/cut-offs/$id")->assertOk();
        $this->assertDatabaseMissing('cut_offs', ['id' => $id]);
        $this->assertDatabaseCount('cut_off_periods', 0);
    }

    public function test_period_count_and_numbers_follow_the_schedule(): void
    {
        $this->login();
        $semiMonthly = $this->payload();
        $semiMonthly['periods'] = [$semiMonthly['periods'][0]];
        $this->postJson('/api/cut-offs', $semiMonthly)->assertUnprocessable()->assertJsonValidationErrors('periods');
        $monthly = $this->payload();
        $monthly['schedule_type'] = 'monthly';
        $this->postJson('/api/cut-offs', $monthly)->assertUnprocessable()->assertJsonValidationErrors('periods');
        $monthly['periods'] = [$monthly['periods'][1]];
        $this->postJson('/api/cut-offs', $monthly)->assertUnprocessable()->assertJsonValidationErrors('periods');
        $duplicates = $this->payload();
        $duplicates['periods'][1]['cut_off_no'] = 1;
        $this->postJson('/api/cut-offs', $duplicates)->assertUnprocessable()->assertJsonValidationErrors('periods.1.cut_off_no');
        $this->assertDatabaseCount('cut_offs', 0);
        $this->assertDatabaseCount('cut_off_periods', 0);
    }

    public function test_period_ranges_are_validated_against_the_same_period(): void
    {
        $this->login();
        $payload = $this->payload();
        $payload['periods'][1]['dtr_date_to'] = '2026-10-15';
        $payload['periods'][1]['payroll_date_to'] = '2026-10-15';
        $payload['periods'][1]['confirmation_end_date'] = '2026-10-31';
        $payload['periods'][1]['release_date'] = '2026-10-01';
        $this->postJson('/api/cut-offs', $payload)->assertUnprocessable()->assertJsonValidationErrors([
            'periods.1.dtr_date_to', 'periods.1.payroll_date_to', 'periods.1.confirmation_end_date', 'periods.1.release_date',
        ]);
        $payload = $this->payload('monthly');
        $payload['periods'][0]['confirmation_end_date'] = '2026-11-01';
        $payload['periods'][0]['confirmation_time_to'] = '07:00';
        $this->postJson('/api/cut-offs', $payload)->assertUnprocessable()->assertJsonValidationErrors('periods.0.confirmation_time_to');
    }

    public function test_no_dtr_clears_attendance_settings_but_still_requires_payroll(): void
    {
        $this->login();
        $payload = $this->payload();
        $payload['periods'][0]['no_dtr'] = true;
        $payload['periods'][0]['dtr_date_from'] = 'ignored';
        unset($payload['periods'][0]['confirmation_start_date']);
        $response = $this->postJson('/api/cut-offs', $payload)->assertCreated()
            ->assertJsonPath('data.periods.0.no_dtr', true)
            ->assertJsonPath('data.periods.0.dtr_date_from', null)->assertJsonPath('data.periods.0.confirmation_start_date', null)
            ->assertJsonPath('data.periods.0.confirmation_time_from', null)->assertJsonPath('data.periods.0.payroll_date_from', '2026-10-01')
            ->assertJsonPath('data.periods.1.no_dtr', false)->assertJsonPath('data.periods.1.dtr_date_from', '2026-10-16');
        $id = $response->json('data.id');
        $this->putJson("/api/cut-offs/$id", $this->payload())->assertOk()->assertJsonPath('data.periods.0.no_dtr', false);
        unset($payload['periods'][0]['payroll_date_from']);
        $this->postJson('/api/cut-offs', $payload)->assertUnprocessable()->assertJsonValidationErrors('periods.0.payroll_date_from');
        $missingDtr = $this->payload('monthly');
        unset($missingDtr['periods'][0]['dtr_date_from']);
        $this->postJson('/api/cut-offs', $missingDtr)->assertUnprocessable()->assertJsonValidationErrors('periods.0.dtr_date_from');
    }

    public function test_invalid_updates_preserve_the_parent_and_existing_periods(): void
    {
        $this->login();
        $id = $this->postJson('/api/cut-offs', $this->payload())->assertCreated()->json('data.id');
        $before = CutOff::with('periods')->findOrFail($id)->toArray();
        $invalid = $this->payload('monthly');
        $invalid['name'] = 'Must not be saved';
        $invalid['periods'][0]['cut_off_no'] = 2;
        $this->putJson("/api/cut-offs/$id", $invalid)->assertUnprocessable();
        $this->assertSame($before, CutOff::with('periods')->findOrFail($id)->toArray());
    }

    public function test_related_writes_roll_back_when_a_child_write_fails(): void
    {
        $this->login();
        $cutOff = app(CutOffService::class)->createCutOff($this->payload());
        $before = CutOff::with('periods')->findOrFail($cutOff->id)->toArray();
        CutOffPeriod::creating(fn () => throw new \RuntimeException('Simulated child write failure'));
        try {
            app(CutOffService::class)->updateCutOff($this->payload('monthly'), (string) $cutOff->id);
            $this->fail('The child write should fail.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated child write failure', $error->getMessage());
        } finally {
            CutOffPeriod::flushEventListeners();
        }
        $this->assertSame($before, CutOff::with('periods')->findOrFail($cutOff->id)->toArray());
    }

    public function test_new_contract_rejects_legacy_and_unknown_fields(): void
    {
        $this->login();
        foreach ([['schedule_type' => 'semi-monthly'], ['quarter' => 4], ['month' => 13], ['year' => 0], ['releases' => [['cutoff_no' => 1]]]] as $change) {
            $this->postJson('/api/cut-offs', [...$this->payload(), ...$change])->assertUnprocessable()->assertJsonValidationErrors(array_keys($change));
        }
        $payload = $this->payload();
        $payload['periods'][0]['cut_off_id'] = 999;
        $this->postJson('/api/cut-offs', $payload)->assertUnprocessable()->assertJsonValidationErrors('periods.0');
    }

    public function test_dtr_and_payroll_ranges_are_independent_and_can_cross_months(): void
    {
        $this->login();
        $payload = $this->payload('monthly');
        $payload['periods'][0]['dtr_date_from'] = '2026-09-26';
        $payload['periods'][0]['dtr_date_to'] = '2026-10-25';
        $this->postJson('/api/cut-offs', $payload)->assertCreated()
            ->assertJsonPath('data.periods.0.dtr_date_from', '2026-09-26')
            ->assertJsonPath('data.periods.0.dtr_date_to', '2026-10-25')
            ->assertJsonPath('data.periods.0.payroll_date_from', '2026-10-01')
            ->assertJsonPath('data.periods.0.payroll_date_to', '2026-10-31');
    }

    public function test_permissions_and_missing_records_are_enforced(): void
    {
        $this->getJson('/api/cut-offs')->assertUnauthorized();
        $this->login('employee');
        $this->getJson('/api/cut-offs')->assertForbidden();
        $this->postJson('/api/cut-offs', $this->payload())->assertForbidden();
        $this->putJson('/api/cut-offs/1', $this->payload())->assertForbidden();
        $this->deleteJson('/api/cut-offs/1')->assertForbidden();
        $this->login();
        $this->getJson('/api/cut-offs/999')->assertNotFound();
        $this->putJson('/api/cut-offs/999', $this->payload())->assertNotFound();
        $this->deleteJson('/api/cut-offs/999')->assertNotFound();
        $this->getJson('/api/payroll-cutoffs')->assertNotFound();
    }

    public function test_view_only_permission_does_not_allow_writes(): void
    {
        $role = Role::factory()->create();
        $role->menus()->attach(Menu::where('code', 'cut_off')->value('id'), ['can_view' => true]);
        Sanctum::actingAs(User::factory()->forEmployee(Employee::factory()->create())->withRole($role)->create());
        $this->getJson('/api/cut-offs')->assertOk();
        $this->postJson('/api/cut-offs', $this->payload())->assertForbidden();
        $this->putJson('/api/cut-offs/1', $this->payload())->assertForbidden();
        $this->deleteJson('/api/cut-offs/1')->assertForbidden();
    }

    public function test_filter_sort_and_pagination_contract_includes_periods(): void
    {
        $this->login();
        $first = CutOff::factory()->semiMonthly()->withPeriods()->create(['name' => 'October A', 'year' => 2026, 'month' => 10, 'is_active' => false, 'created_at' => '2026-10-01']);
        $second = CutOff::factory()->semiMonthly()->withPeriods()->create(['name' => 'October B', 'year' => 2026, 'month' => 10, 'is_active' => false, 'created_at' => '2026-10-02']);
        CutOff::factory()->withPeriods()->create(['name' => 'October monthly', 'year' => 2026, 'month' => 10]);
        CutOff::factory()->semiMonthly()->withPeriods()->create(['year' => 2026, 'month' => 11]);
        $query = ['search' => 'October', 'schedule_type' => 'semi_monthly', 'year' => 2026, 'month' => 10, 'is_active' => 'false', 'date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'sort_by' => 'id', 'sort_order' => 'asc'];
        $this->getJson('/api/cut-offs?'.http_build_query($query))->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->id)->assertJsonCount(2, 'data.0.periods');
        $this->getJson('/api/cut-offs?'.http_build_query([...$query, 'limit' => 1, 'offset' => 1]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second->id);
        $this->getJson('/api/cut-offs?sort_by=invalid&sort_order=invalid&limit=0&offset=-1')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_factories_and_seeder_use_the_new_two_table_schema(): void
    {
        $this->assertFalse(Schema::hasTable('payroll_cutoffs'));
        $this->assertFalse(Schema::hasTable('payroll_cutoff_releases'));
        $monthly = CutOff::factory()->withPeriods()->create(['year' => 2028, 'month' => 2]);
        $this->assertSame(1, $monthly->periods()->count());
        $this->assertSame('2028-02-29', $monthly->periods()->firstOrFail()->payroll_date_to->toDateString());
        CutOff::query()->delete();
        $this->seed(CutOffSeeder::class);
        $this->seed(CutOffSeeder::class);
        $this->assertDatabaseCount('cut_offs', 1);
        $this->assertDatabaseCount('cut_off_periods', 2);
    }

    public function test_list_limits_and_offset_remain_bounded(): void
    {
        $this->login();
        CutOff::factory()->withPeriods()->count(101)->create();
        $this->getJson('/api/cut-offs')->assertOk()->assertJsonCount(15, 'data');
        $this->getJson('/api/cut-offs?limit=999')->assertOk()->assertJsonCount(100, 'data');
        $this->getJson('/api/cut-offs?limit=1&offset=101')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_period_seeder_fills_missing_periods_without_replacing_existing_settings(): void
    {
        $monthly = CutOff::factory()->create(['year' => 2028, 'month' => 2]);
        $semiMonthly = CutOff::factory()->semiMonthly()->create(['year' => 2028, 'month' => 2]);
        $first = CutOffPeriod::factory()->forCutOff($semiMonthly)->withoutDtr()->create(['release_date' => '2028-02-23']);
        $before = $first->fresh()->toArray();
        $this->seed(CutOffPeriodSeeder::class);
        $this->seed(CutOffPeriodSeeder::class);

        $this->assertSame($before, $first->fresh()->toArray());
        $this->assertSame(1, $monthly->periods()->count());
        $this->assertSame(2, $semiMonthly->periods()->count());
        $this->assertSame('2028-02-29', $semiMonthly->periods()->where('cut_off_no', 2)->firstOrFail()->payroll_date_to->toDateString());
    }

    public function test_database_seeder_populates_cut_off_tables_and_menu_on_a_fresh_schema(): void
    {
        $this->seed(DatabaseSeeder::class);
        $cutOff = CutOff::with('periods')->firstOrFail();
        $this->assertSame('semi_monthly', $cutOff->schedule_type);
        $this->assertSame([1, 2], $cutOff->periods->pluck('cut_off_no')->all());
        $this->assertDatabaseCount('cut_offs', 1);
        $this->assertDatabaseCount('cut_off_periods', 2);
        $menu = Menu::where('code', 'cut_off')->firstOrFail();
        $this->assertSame('/cut-offs', $menu->path);
        $admin = User::whereHas('role', fn ($query) => $query->where('code', 'admin'))->firstOrFail();
        $this->assertTrue($admin->hasMenuAccess('cut_off', 'view'));
        $this->assertTrue($admin->hasMenuAccess('cut_off', 'delete'));
        $this->assertDatabaseMissing('menus', ['code' => 'payroll_cutoff']);
        $this->assertFalse(Schema::hasTable('payroll_cutoffs'));
        $this->assertFalse(Schema::hasTable('payroll_cutoff_releases'));
    }
}
