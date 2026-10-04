<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Requests\EmployeeScheduleRequest;
use App\Models\Employee;
use App\Services\EmployeeScheduleService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EmployeeScheduleController extends Controller
{
    public function __construct(private EmployeeScheduleService $empSchedService) {}

    public function index(Request $request, string $employee_id)
    {
        $assignments = $this->empSchedService->findAll($employee_id);

        return response()->json(['message' => 'success', 'data' => $assignments]);
    }

    public function find(EmployeeScheduleRequest $request, string $employee_id)
    {
        $schedule = $this->empSchedService->findForRange($employee_id, $request->validated());

        return response()->json(['message' => 'success', 'data' => $schedule]);
    }

    public function store(EmployeeScheduleRequest $request)
    {
        $assignments = $this->empSchedService->assignSchedule($request->validated(), $request->user()->id);

        return response()->json(['message' => 'Schedule assigned successfully.', 'data' => $assignments], 201);
    }

    public function update(EmployeeScheduleRequest $request, string $assignment_id)
    {
        $assignment = $this->empSchedService->updateAssignment($request->validated(), $assignment_id);

        return response()->json(['message' => 'Assignment end date updated successfully.', 'data' => $assignment]);
    }
}