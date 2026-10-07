<?php

namespace App\Services\Attendance;

use App\Helpers\DateHelper;
use App\Pipelines\Attendance\AttendanceProcess;
use Carbon\Carbon;
use Illuminate\Pipeline\Pipeline;

class EmployeeAttendanceService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function processLogs(array $data): array
    {
        $from = Carbon::createFromFormat('Y-m-d', $data['from'])->startOfDay();
        $to = Carbon::createFromFormat('Y-m-d', $data['to'])->startOfDay();
        $date_range = DateHelper::dateRange($from, $to);
        $results = [];
        foreach ($date_range as $date) {
            $processed = app(Pipeline::class)
                ->send([
                    'employee_id' => $data['employee_id'],
                    'date' => $date,
                ])
                ->through([AttendanceProcess::class])
                ->thenReturn();

            if ($processed['result'] !== null) {
                array_push($results, ...$processed['result']);
            } else {
                $results[] = [
                    'date' => $date,
                    'sched_start' => null,
                    'sched_end' => null,
                    'time_in' => null,
                    'time_out' => null,
                    'scheduled_seconds' => 0,
                    'rendered_seconds' => 0,
                    'tardy_seconds' => 0,
                    'undertime_seconds' => 0,
                    'early_out_seconds' => 0,
                    'absent' => false,
                    'remarks' => $processed['remarks'],
                ];
            }
        }

        return $results;
    }
}
