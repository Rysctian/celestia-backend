<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Requests\AttendancePolicyRequest;
use App\Services\Attendance\AttendancePolicyService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AttendancePolicyController extends Controller
{
    public function __construct(private AttendancePolicyService $policyService) {}

    public function index(Request $request)
    {
        return response()->json([
            'message' => 'success',
            'data' => $this->policyService->search($request),
        ]);
    }

    public function find(string $policy_id)
    {
        $policy = $this->policyService->findById($policy_id);
        if (! $policy) {
            return response()->json(['message' => 'Attendance policy not found.'], 404);
        }

        return response()->json(['message' => 'success', 'data' => $policy]);
    }

    public function store(AttendancePolicyRequest $request)
    {
        $policy = $this->policyService->createPolicy($request->validated());

        return response()->json([
            'message' => 'Attendance policy created successfully.',
            'data' => $policy,
        ], 201);
    }

    public function update(AttendancePolicyRequest $request, string $policy_id)
    {
        $policy = $this->policyService->updatePolicy($request->validated(), $policy_id);

        return response()->json([
            'message' => 'Attendance policy updated successfully.',
            'data' => $policy,
        ]);
    }

    public function destroy(string $policy_id)
    {
        $this->policyService->deletePolicy($policy_id);

        return response()->json(['message' => 'Attendance policy deleted successfully.']);
    }
}
