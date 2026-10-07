<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Requests\AttendanceReadRequest;
use App\Models\Timesheet;
use Illuminate\Routing\Controller;

class TimesheetController extends Controller
{
    public function index(AttendanceReadRequest $request)
    {
        return response()->json(['message' => 'success', 'data' => Timesheet::with('employeeLog')->filterSearch($request, 'attendance_date')->get()]);
    }
}
