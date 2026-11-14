<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class EmployeeLogImportTest extends TestCase
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

    private function makeExcelUpload(array $rows, string $filename = 'logs.xlsx'): UploadedFile
    {
        $export = new class($rows) implements FromArray
        {
            public function __construct(private array $rows) {}

            public function array(): array
            {
                return [
                    ['DATE', 'EMPLOYEE ID', 'NAME', 'TIME IN', 'TIME OUT', 'TIME IN', 'TIME OUT'],
                    ['', '', '', '(AM)', '(AM)', '(PM)', '(PM)'],
                    ...$this->rows,
                ];
            }
        };

        $binary = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
        $path = tempnam(sys_get_temp_dir(), 'log_import_').'.xlsx';
        file_put_contents($path, $binary);

        return new UploadedFile(
            $path,
            $filename,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    public function test_permissions_template_download_and_log_listing(): void
    {
        $this->actingAsRole('employee');
        $this->getJson('/api/employee-logs')->assertForbidden();
        $this->getJson('/api/employee-logs/template')->assertForbidden();

        $this->actingAsRole('admin');
        $response = $this->get('/api/employee-logs/template');
        $response->assertOk();
        $this->assertStringContainsString('attendance-in-out-template.xlsx', (string) $response->headers->get('content-disposition'));
    }

    public function test_excel_import_inserts_employee_logs_and_skips_duplicates(): void
    {
        $admin = $this->actingAsRole('admin');
        $employeeId = $admin->employee_id;

        $file = $this->makeExcelUpload([
            ['9/14/2024', $employeeId, 'DOE, JANE', '8:00 AM', '12:00 PM', '1:00 PM', '4:00 PM'],
            ['9/14/2024', $employeeId, 'DOE, JANE', '8:00 AM', '', '', ''],
        ]);

        $this->postJson('/api/employee-logs/import', ['file' => $file])
            ->assertCreated()
            ->assertJsonPath('data.imported_count', 4)
            ->assertJsonPath('data.skipped_count', 1)
            ->assertJsonPath('data.total_rows', 5);

        $this->assertDatabaseCount('employee_logs', 4);
        $this->assertDatabaseHas('employee_logs', [
            'employee_id' => $employeeId,
            'logged_at' => '2024-09-14 08:00:00',
            'source' => 'excel_import',
        ]);
        $this->assertDatabaseHas('employee_logs', [
            'employee_id' => $employeeId,
            'logged_at' => '2024-09-14 16:00:00',
            'source' => 'excel_import',
        ]);

        $this->getJson('/api/employee-logs?source=excel_import')
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_invalid_rows_reject_the_entire_upload(): void
    {
        $this->actingAsRole('admin');

        $file = $this->makeExcelUpload([
            ['9/14/2024', 'NON-EXISTENT-EMP', 'DOE, JANE', '8:00 AM', '12:00 PM', '1:00 PM', '4:00 PM'],
        ]);

        $this->postJson('/api/employee-logs/import', ['file' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file', 'rows']);

        $this->assertSame(0, EmployeeLog::count());
    }

    public function test_manual_employee_log_creation_and_duplicate_guard(): void
    {
        $this->actingAsRole('employee');
        $this->postJson('/api/employee-logs', [
            'employee_id' => 'EMP-TEST',
            'logged_at' => '2024-09-14 08:00:00',
        ])->assertForbidden();

        $admin = $this->actingAsRole('admin');

        $this->postJson('/api/employee-logs', [
            'employee_id' => $admin->employee_id,
            'logged_at' => '2024-09-14 08:00:00',
            'device_id' => 'Front Desk',
        ])
            ->assertCreated()
            ->assertJsonPath('data.employee_id', $admin->employee_id)
            ->assertJsonPath('data.device_id', 'Front Desk')
            ->assertJsonPath('data.source', 'manual');

        $this->postJson('/api/employee-logs', [
            'employee_id' => $admin->employee_id,
            'logged_at' => '2024-09-14 08:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logged_at']);

        $this->assertDatabaseHas('employee_logs', [
            'employee_id' => $admin->employee_id,
            'logged_at' => '2024-09-14 08:00:00',
            'device_id' => 'Front Desk',
            'source' => 'manual',
        ]);
    }
}
