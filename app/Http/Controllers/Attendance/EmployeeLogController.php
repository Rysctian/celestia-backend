<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Requests\AttendanceReadRequest;
use App\Http\Requests\EmployeeLogRequest;
use App\Models\EmployeeLog;
use App\Services\Attendance\EmployeeLogService;
use Illuminate\Routing\Controller;

class EmployeeLogController extends Controller
{
    public function __construct(private EmployeeLogService $logs) {}

    public function index(AttendanceReadRequest $request)
    {
        return response()->json(['message' => 'success', 'data' => EmployeeLog::filterSearch($request, 'logged_at')->get()]);
    }

    public function store(EmployeeLogRequest $request)
    {
        $log = $this->logs->capture($request->validated());

        return response()->json(['message' => 'Attendance tap captured.', 'data' => $log], $log->wasRecentlyCreated ? 201 : 200);
    }
}
