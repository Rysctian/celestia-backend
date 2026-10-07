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
        $data = $request->validated();
        $result = $this->attendance->processLogs($data);

        return response()->json([
            'message' => 'Attendance processed successfully.',
            'date-from' => $data['from'],
            'date-to' => $data['to'],
            'data' => $result,
        ]);
    }
}
