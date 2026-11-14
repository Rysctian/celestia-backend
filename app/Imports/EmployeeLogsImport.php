<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\EmployeeLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class EmployeeLogsImport implements SkipsEmptyRows, ToCollection
{
    private int $importedCount = 0;

    private int $skippedCount = 0;

    private int $totalRows = 0;

    private const TIME_COLUMNS = [
        3 => 'TIME IN (AM)',
        4 => 'TIME OUT (AM)',
        5 => 'TIME IN (PM)',
        6 => 'TIME OUT (PM)',
    ];

    public function collection(Collection $rows): void
    {
        $dataRows = $rows
            ->map(fn ($row, $index) => ['row_number' => $index + 1, 'cells' => $row])
            ->filter(fn ($entry) => ! $this->isHeaderRow($entry['cells']))
            ->values();

        if ($dataRows->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded Excel file contains no attendance log rows.',
            ]);
        }

        $employeeIds = $dataRows
            ->map(fn ($entry) => trim((string) ($entry['cells'][1] ?? '')))
            ->filter()
            ->unique()
            ->values();

        $existingEmployees = Employee::whereIn('employee_id', $employeeIds)
            ->pluck('employee_id')
            ->flip();

        $parsedRows = [];
        $errors = [];

        foreach ($dataRows as $entry) {
            $spreadsheetRow = $entry['row_number'];
            $cells = $entry['cells'];
            $rawDate = $cells[0] ?? null;
            $employeeId = trim((string) ($cells[1] ?? ''));

            if ($employeeId === '') {
                $errors[] = "Row {$spreadsheetRow}: EMPLOYEE ID is required.";
                continue;
            }

            if (mb_strlen($employeeId) > 30) {
                $errors[] = "Row {$spreadsheetRow}: EMPLOYEE ID exceeds 30 characters.";
                continue;
            }

            if (! $existingEmployees->has($employeeId)) {
                $errors[] = "Row {$spreadsheetRow}: EMPLOYEE ID '{$employeeId}' does not exist.";
                continue;
            }

            $date = $this->parseDate($rawDate);
            if (! $date) {
                $errors[] = "Row {$spreadsheetRow}: DATE must be a valid date (e.g. 9/14/2024 or 2024-09-14).";
                continue;
            }

            $rowHasPunch = false;
            $rowInvalid = false;

            foreach (self::TIME_COLUMNS as $colIndex => $label) {
                $rawTime = $cells[$colIndex] ?? null;
                if ($rawTime === null || trim((string) $rawTime) === '') {
                    continue;
                }

                $rowHasPunch = true;
                $loggedAt = $this->parseDateTime($date, $rawTime);
                if (! $loggedAt) {
                    $errors[] = "Row {$spreadsheetRow}: {$label} must be a valid time (e.g. 8:00 AM or 08:00).";
                    $rowInvalid = true;
                    continue;
                }

                $parsedRows[] = [
                    'employee_id' => $employeeId,
                    'logged_at' => $loggedAt->format('Y-m-d H:i:s'),
                    'device_id' => 'EXCEL-UPLOAD',
                    'source' => 'excel_import',
                ];
            }

            if (! $rowHasPunch && ! $rowInvalid) {
                $errors[] = "Row {$spreadsheetRow}: at least one time column (TIME IN/OUT AM or PM) is required.";
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages([
                'file' => $errors[0],
                'rows' => $errors,
            ]);
        }

        $this->totalRows = count($parsedRows);

        DB::transaction(function () use ($parsedRows) {
            $now = now();
            $seenInFile = [];
            $toInsert = [];

            $minDate = min(array_column($parsedRows, 'logged_at'));
            $maxDate = max(array_column($parsedRows, 'logged_at'));
            $employeeIds = array_values(array_unique(array_column($parsedRows, 'employee_id')));

            $existingSet = EmployeeLog::whereIn('employee_id', $employeeIds)
                ->whereBetween('logged_at', [$minDate, $maxDate])
                ->get(['employee_id', 'logged_at'])
                ->mapWithKeys(fn ($log) => [$log->employee_id.'|'.$log->logged_at->format('Y-m-d H:i:s') => true]);

            foreach ($parsedRows as $entry) {
                $key = $entry['employee_id'].'|'.$entry['logged_at'];

                if (isset($seenInFile[$key]) || $existingSet->has($key)) {
                    $this->skippedCount++;
                    continue;
                }

                $seenInFile[$key] = true;
                $toInsert[] = [
                    ...$entry,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! empty($toInsert)) {
                EmployeeLog::insert($toInsert);
                $this->importedCount = count($toInsert);
            }
        });
    }

    public function summary(): array
    {
        return [
            'imported_count' => $this->importedCount,
            'skipped_count' => $this->skippedCount,
            'total_rows' => $this->totalRows,
        ];
    }

    private function isHeaderRow(Collection|array $cells): bool
    {
        $col0 = strtoupper(trim((string) ($cells[0] ?? '')));
        $col1 = strtoupper(trim((string) ($cells[1] ?? '')));
        $col3 = strtoupper(trim((string) ($cells[3] ?? '')));

        if ($col0 === 'DATE' && str_contains($col1, 'EMPLOYEE')) {
            return true;
        }

        if ($col0 === '' && $col1 === '' && str_contains($col3, 'AM')) {
            return true;
        }

        return false;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
            }

            return Carbon::parse(trim((string) $value))->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function parseDateTime(Carbon $date, mixed $value): ?Carbon
    {
        try {
            if (is_numeric($value)) {
                $excelTime = Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));

                return $date->copy()->setTime($excelTime->hour, $excelTime->minute, $excelTime->second);
            }

            $time = Carbon::parse(trim((string) $value));

            return $date->copy()->setTime($time->hour, $time->minute, $time->second);
        } catch (Throwable) {
            return null;
        }
    }
}
