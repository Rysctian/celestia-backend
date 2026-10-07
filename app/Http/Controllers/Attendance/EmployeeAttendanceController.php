<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Requests\AttendanceApprovalRequest;
use App\Http\Requests\AttendanceCorrectionRequest;
use App\Http\Requests\AttendanceExceptionRequest;
use App\Http\Requests\AttendanceProcessRequest;
use App\Http\Requests\AttendanceReadRequest;
use App\Models\EmployeeAttendance;
use App\Services\Attendance\AttendanceProcessor;
use App\Services\Attendance\AttendanceReviewService;
use Illuminate\Routing\Controller;

class EmployeeAttendanceController extends Controller
{
    public function __construct(private AttendanceProcessor $processor, private AttendanceReviewService $reviews) {}

    public function index(AttendanceReadRequest $request)
    {
        return response()->json(['message' => 'success', 'data' => EmployeeAttendance::with('scheduleDetail', 'corrections')->filterSearch($request)->get()]);
    }

    public function process(AttendanceProcessRequest $request)
    {
        $data = $request->validated();
        $result = $this->processor->reprocessAttendance($data['employee_id'], $data['date']);

        return response()->json(['message' => 'Attendance processed.', 'data' => $result]);
    }

    public function correct(AttendanceCorrectionRequest $request, int $attendance_id)
    {
        $attendance = $this->reviews->correct($attendance_id, $request->validated(), $request->user()->id);

        return response()->json(['message' => 'Attendance correction approved.', 'data' => $attendance]);
    }

    public function exception(AttendanceExceptionRequest $request)
    {
        $exception = $this->reviews->approveException($request->validated(), $request->user()->id);

        return response()->json(['message' => 'Attendance exception approved.', 'data' => $exception], 201);
    }

    public function approveOvertime(AttendanceApprovalRequest $request, int $overtime_id)
    {
        return response()->json(['message' => 'Overtime approved.', 'data' => $this->reviews->approveOvertime($overtime_id, $request->user()->id)]);
    }
}
