<?php

namespace App\Http\Controllers\OnlineApplication;

use App\Http\Requests\OnlineApplication\ApplicationDecisionRequest;
use App\Http\Requests\OnlineApplication\LeaveApplicationRequest;
use App\Services\OnlineApplication\LeaveApplicationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LeaveApplicationController extends Controller
{
    public function __construct(private LeaveApplicationService $leaves) {}

    public function index(Request $request)
    {
        return response()->json([
            'message' => 'success',
            'data' => $this->leaves->findAll($request, $request->user()),
        ]);
    }

    public function balances(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'string', 'exists:employees,employee_id'],
            'year' => ['nullable', 'integer', 'between:2020,2100'],
        ]);

        $employeeId = $validated['employee_id'] ?? $request->user()?->employee_id;
        abort_if(! $employeeId, 422, 'Select an employee to view leave balances.');
        $year = (int) ($validated['year'] ?? now()->year);

        return response()->json([
            'message' => 'success',
            'data' => $this->leaves->getBalances($employeeId, $year, $request->user()),
        ]);
    }

    public function find(Request $request, int $application_id)
    {
        return response()->json([
            'message' => 'success',
            'data' => $this->leaves->findById($application_id, $request->user()),
        ]);
    }

    public function store(LeaveApplicationRequest $request)
    {
        return response()->json([
            'message' => 'Leave application submitted.',
            'data' => $this->leaves->submit($request->validated(), $request->user(), $request->file('attachment')),
        ], 201);
    }

    public function attachment(Request $request, int $application_id)
    {
        return $this->leaves->downloadAttachment($application_id, $request->user());
    }

    public function approve(ApplicationDecisionRequest $request, int $application_id)
    {
        return response()->json([
            'message' => 'Leave approval recorded.',
            'data' => $this->leaves->decide($application_id, $request->user(), 'approved', $request->validated('remarks')),
        ]);
    }

    public function disapprove(ApplicationDecisionRequest $request, int $application_id)
    {
        return response()->json([
            'message' => 'Leave application disapproved.',
            'data' => $this->leaves->decide($application_id, $request->user(), 'disapproved', $request->validated('remarks')),
        ]);
    }

    public function cancel(ApplicationDecisionRequest $request, int $application_id)
    {
        return response()->json([
            'message' => 'Leave application cancelled.',
            'data' => $this->leaves->cancel($application_id, $request->user(), $request->validated('remarks')),
        ]);
    }
}
