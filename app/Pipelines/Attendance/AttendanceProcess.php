<?php

namespace App\Pipelines\Attendance;

use App\Services\Attendance\EmployeeProcessLogsService;
use Closure;

class AttendanceProcess
{
    public function __construct(private EmployeeProcessLogsService $employeeProcessLogs) {}

    public function handle(array $data, Closure $next): mixed
    {
        $result = $this->employeeProcessLogs->process($data['employee_id'], $data['date']);

        return $next($result);
    }
}
