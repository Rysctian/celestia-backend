<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use App\Models\EmployeeLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeLogService
{
    public function capture(array $data): EmployeeLog
    {
        return DB::transaction(function () use ($data) {
            Employee::where('employee_id', $data['employee_id'])->lockForUpdate()->firstOrFail();
            $data['logged_at'] = CarbonImmutable::parse($data['logged_at'])->utc()->toDateTimeString();
            $data['external_log_id'] = $data['external_log_id'] ?? null;

            if ($data['external_log_id'] !== null) {
                // firstOrCreate also handles concurrent retries using the database unique constraint.
                $log = EmployeeLog::firstOrCreate(['source' => $data['source'], 'external_log_id' => $data['external_log_id']], $data);
                if ($log->employee_id !== $data['employee_id'] || $log->logged_at->toDateTimeString() !== $data['logged_at']
                    || $log->device_id !== ($data['device_id'] ?? null)
                    || (array_key_exists('raw_payload', $data) && $log->raw_payload !== $data['raw_payload'])) {
                    throw ValidationException::withMessages(['external_log_id' => 'This external ID already belongs to a different tap.']);
                }

                return $log;
            }

            return EmployeeLog::create($data);
        });
    }
}
