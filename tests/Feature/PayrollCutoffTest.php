<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayrollCutoff;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PayrollCutoffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayrollCutoffTest extends TestCase
{
    use RefreshDatabase;

    private function login(): void
    {
        $employee = Employee::factory()->create(['type' => 'admin']);
        $user = User::factory()->forEmployee($employee)->create([
            'role_id' => Role::where('code', 'admin')->firstOrFail()->id,
        ]);

        Sanctum::actingAs($user);
    }

    private function payload(): array
    {
        return [
            'schedule_type' => 'semi-monthly',
            'quarter' => 4,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'no_dtr' => false,
            'dtr_cutoff_from' => '2026-10-01',
            'dtr_cutoff_to' => '2026-10-31',
            'dtr_confirmation_start' => '2026-11-01',
            'dtr_confirmation_end' => '2026-11-02',
            'dtr_confirmation_time_from' => '08:00',
            'dtr_confirmation_time_to' => '17:00',
            'is_active' => true,
            'releases' => [
                ['cutoff_no' => 1, 'release_date' => '2026-10-15'],
                ['cutoff_no' => 2, 'release_date' => '2026-10-31'],
            ],
        ];
    }

    public function test_admin_can_manage_payroll_cutoffs(): void
    {
        $this->login();

        $id = $this->postJson('/api/payroll-cutoffs', $this->payload())
            ->assertCreated()
            ->assertJsonCount(2, 'data.releases')
            ->assertJsonPath('data.schedule_type', 'semi-monthly')
            ->json('data.id');

        $this->getJson('/api/payroll-cutoffs?schedule_type=semi-monthly')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->getJson("/api/payroll-cutoffs/{$id}")
            ->assertOk()
            ->assertJsonPath('data.quarter', 4);

        $payload = $this->payload();
        $payload['no_dtr'] = true;
        $payload['dtr_cutoff_from'] = null;
        $payload['dtr_cutoff_to'] = null;
        $payload['releases'][1]['release_date'] = '2026-11-05';

        $this->putJson("/api/payroll-cutoffs/{$id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.no_dtr', true)
            ->assertJsonPath('data.releases.1.release_date', '2026-11-05');

        $this->deleteJson("/api/payroll-cutoffs/{$id}")->assertOk();
        $this->assertDatabaseMissing('payroll_cutoffs', ['id' => $id]);
        $this->assertDatabaseCount('payroll_cutoff_releases', 0);
    }

    public function test_dtr_dates_are_required_unless_no_dtr_is_enabled(): void
    {
        $this->login();
        $payload = $this->payload();
        $payload['dtr_cutoff_from'] = null;
        $payload['dtr_cutoff_to'] = null;

        $this->postJson('/api/payroll-cutoffs', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dtr_cutoff_from', 'dtr_cutoff_to']);

        $payload['no_dtr'] = true;
        $this->postJson('/api/payroll-cutoffs', $payload)->assertCreated();
    }

    public function test_factory_and_seeder_create_releases(): void
    {
        PayrollCutoff::factory()->withReleases()->create();

        $this->assertDatabaseCount('payroll_cutoffs', 1);
        $this->assertDatabaseCount('payroll_cutoff_releases', 2);

        PayrollCutoff::query()->delete();
        $this->seed(PayrollCutoffSeeder::class);
        $this->seed(PayrollCutoffSeeder::class);

        $this->assertDatabaseCount('payroll_cutoffs', 1);
        $this->assertDatabaseCount('payroll_cutoff_releases', 2);
    }
}
