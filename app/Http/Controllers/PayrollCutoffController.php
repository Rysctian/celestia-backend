<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayrollCutoffRequest;
use App\Services\PayrollCutoffService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PayrollCutoffController extends Controller
{
    public function __construct(private PayrollCutoffService $payrollCutoffService) {}

    public function index(Request $request)
    {
        return response()->json([
            'message' => 'success',
            'data' => $this->payrollCutoffService->search($request),
        ]);
    }

    public function find(string $payroll_cutoff_id)
    {
        $payrollCutoff = $this->payrollCutoffService->findById($payroll_cutoff_id);
        if (! $payrollCutoff) {
            return response()->json(['message' => 'Payroll cut-off not found.'], 404);
        }

        return response()->json(['message' => 'success', 'data' => $payrollCutoff]);
    }

    public function store(PayrollCutoffRequest $request)
    {
        $payrollCutoff = $this->payrollCutoffService->createPayrollCutoff($request->validated());

        return response()->json(['message' => 'Payroll cut-off created successfully.', 'data' => $payrollCutoff], 201);
    }

    public function update(PayrollCutoffRequest $request, string $payroll_cutoff_id)
    {
        $payrollCutoff = $this->payrollCutoffService->updatePayrollCutoff($request->validated(), $payroll_cutoff_id);

        return response()->json(['message' => 'Payroll cut-off updated successfully.', 'data' => $payrollCutoff]);
    }

    public function destroy(string $payroll_cutoff_id)
    {
        $this->payrollCutoffService->deletePayrollCutoff($payroll_cutoff_id);

        return response()->json(['message' => 'Payroll cut-off deleted successfully.']);
    }
}
