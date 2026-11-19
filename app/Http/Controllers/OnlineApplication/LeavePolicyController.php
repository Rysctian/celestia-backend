<?php

namespace App\Http\Controllers\OnlineApplication;

use App\Http\Requests\OnlineApplication\LeavePolicyRequest;
use App\Services\OnlineApplication\LeaveApplicationService;
use App\Services\OnlineApplication\LeavePolicyService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LeavePolicyController extends Controller
{
    public function __construct(
        private LeavePolicyService $policies,
        private LeaveApplicationService $leaves,
    ) {}

    public function index(Request $request)
    {
        abort_unless($this->leaves->isAdministrator($request->user()), 403, 'Only administrators can manage leave policies.');

        return response()->json([
            'message' => 'success',
            'data' => $this->policies->findAll($request),
        ]);
    }

    public function find(Request $request, int $policy_id)
    {
        abort_unless($this->leaves->isAdministrator($request->user()), 403, 'Only administrators can view leave policy details.');

        return response()->json([
            'message' => 'success',
            'data' => $this->policies->findById($policy_id),
        ]);
    }

    public function store(LeavePolicyRequest $request)
    {
        abort_unless($this->leaves->isAdministrator($request->user()), 403, 'Only administrators can create leave policies.');

        return response()->json([
            'message' => 'Leave policy created.',
            'data' => $this->policies->create($request->validated()),
        ], 201);
    }

    public function update(LeavePolicyRequest $request, int $policy_id)
    {
        abort_unless($this->leaves->isAdministrator($request->user()), 403, 'Only administrators can update leave policies.');

        return response()->json([
            'message' => 'Leave policy updated.',
            'data' => $this->policies->update($policy_id, $request->validated()),
        ]);
    }

    public function destroy(Request $request, int $policy_id)
    {
        abort_unless($this->leaves->isAdministrator($request->user()), 403, 'Only administrators can delete leave policies.');

        $this->policies->destroy($policy_id);

        return response()->json([
            'message' => 'Leave policy deleted.',
        ]);
    }
}
