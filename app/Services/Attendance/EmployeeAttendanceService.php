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
            $results[] = app(Pipeline::class)
                ->send([
                    'employee_id' => $data['employee_id'],
                    'date' => $date,
                ])
                ->through([AttendanceProcess::class])
                ->thenReturn();
        }

        return $results;
    }
}
