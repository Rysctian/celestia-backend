<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Requests\EmployeeAttendanceRequest;
use App\Services\Attendance\EmployeeAttendanceService;
use Illuminate\Routing\Controller;

class EmployeeAttendanceController extends Controller
{
    public function __construct(private EmployeeAttendanceService $attendance) {}

    public function showAttendance(EmployeeAttendanceRequest $request)
    {
        $result = $this->attendance->processLogs($request->validated());

        return response()->json([
            'message' => 'Attendance processed successfully.',
            'data' => $result,
        ]);
    }
}
