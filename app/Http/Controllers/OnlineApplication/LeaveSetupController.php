<?php

namespace App\Http\Controllers\OnlineApplication;

use App\Http\Requests\OnlineApplication\EmployeeLeaveCreditRequest;
use App\Http\Requests\OnlineApplication\LeaveTypeRequest;
use App\Services\OnlineApplication\LeaveApplicationService;
use App\Services\OnlineApplication\LeaveSetupService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LeaveSetupController extends Controller
{
    public function __construct(
        private LeaveSetupService $setup,
        private LeaveApplicationService $leaves,
    ) {}

    public function types(Request $request)
    {
        abort_unless($this->leaves->isAdministrator($request->user()), 403, 'Only administrators can manage leave types.');

        return response()->json([
            'message' => 'success',
            'data' => $this->setup->findAllTypes($request),
        ]);
    }

    public function storeType(LeaveTypeRequest $request)
    {
        abort_unless($this->leaves->isAdministrator($request->user()), 403, 'Only administrators can create leave types.');

        return response()->json([
            'message' => 'Leave type created.',
            'data' => $this->setup->createType($request->validated()),
        ], 201);
    }

    public function updateType(LeaveTypeRequest $request, int $leave_type_id)
    {
        abort_unless($this->leaves->isAdministrator($request->user()), 403, 'Only administrators can update leave types.');

        return response()->json([
            'message' => 'Leave type updated.',
            'data' => $this->setup->updateType($leave_type_id, $request->validated()),
        ]);
    }

    public function destroyType(Request $request, int $leave_type_id)
    {
        abort_unless($this->leaves->isAdministrator($request->user()), 403, 'Only administrators can delete leave types.');

        $this->setup->deleteType($leave_type_id);

        return response()->json([
            'message' => 'Leave type deleted.',
        ]);
    }

    public function credits(Request $request)
    {
        abort_unless($this->leaves->isAdministrator($request->user()), 403, 'Only administrators can view employee leave credits.');

        return response()->json([
            'message' => 'success',
            'data' => $this->setup->findAllCredits($request),
        ]);
    }

    public function storeCredit(EmployeeLeaveCreditRequest $request)
    {
        abort_unless($this->leaves->isAdministrator($request->user()), 403, 'Only administrators can manage employee leave credits.');

        return response()->json([
            'message' => 'Employee leave credit saved.',
            'data' => $this->setup->upsertCredit($request->validated(), $request->user()?->employee_id),
        ], 201);
    }

    public function updateCredit(EmployeeLeaveCreditRequest $request, int $credit_id)
    {
        abort_unless($this->leaves->isAdministrator($request->user()), 403, 'Only administrators can adjust employee leave credits.');

        return response()->json([
            'message' => 'Employee leave credit updated.',
            'data' => $this->setup->updateCredit($credit_id, $request->validated(), $request->user()?->employee_id),
        ]);
    }

    public function allocateCredits(Request $request)
    {
        abort_unless($this->leaves->isAdministrator($request->user()), 403, 'Only administrators can bulk-allocate leave credits.');

        $validated = $request->validate([
            'year' => ['required', 'integer', 'between:2020,2100'],
            'leave_type_id' => ['nullable', 'integer', 'exists:leave_types,id'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['string', 'exists:employees,employee_id'],
            'overwrite' => ['sometimes', 'boolean'],
        ]);

        return response()->json([
            'message' => 'Annual leave credits allocated successfully.',
            'data' => $this->setup->allocateAnnualCredits($validated, $request->user()?->employee_id),
        ]);
    }

    public function creditLogs(Request $request, int $credit_id)
    {
        abort_unless($this->leaves->isAdministrator($request->user()), 403, 'Only administrators can view credit logs.');

        return response()->json([
            'message' => 'success',
            'data' => $this->setup->getCreditLogs($credit_id),
        ]);
    }
}
