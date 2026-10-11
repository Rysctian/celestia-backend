<?php

namespace App\Http\Controllers\OnlineApplication;

use App\Http\Requests\OnlineApplication\ApplicationDecisionRequest;
use App\Http\Requests\OnlineApplication\OvertimeApplicationRequest;
use App\Services\OnlineApplication\OvertimeApplicationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OvertimeApplicationController extends Controller
{
    public function __construct(private OvertimeApplicationService $overtime) {}

    public function index(Request $request)
    {
        return response()->json(['message' => 'success', 'data' => $this->overtime->findAll($request, $request->user())]);
    }

    public function find(Request $request, int $application_id)
    {
        return response()->json(['message' => 'success', 'data' => $this->overtime->findById($application_id, $request->user())]);
    }

    public function store(OvertimeApplicationRequest $request)
    {
        return response()->json(['message' => 'Overtime submitted.', 'data' => $this->overtime->submit($request->validated(), $request->user())], 201);
    }

    public function approve(ApplicationDecisionRequest $request, int $application_id)
    {
        return response()->json(['message' => 'Overtime approval recorded.', 'data' => $this->overtime->decide($application_id, $request->user(), 'approved', $request->validated('remarks'))]);
    }

    public function disapprove(ApplicationDecisionRequest $request, int $application_id)
    {
        return response()->json(['message' => 'Overtime disapproved.', 'data' => $this->overtime->decide($application_id, $request->user(), 'disapproved', $request->validated('remarks'))]);
    }

    public function cancel(ApplicationDecisionRequest $request, int $application_id)
    {
        return response()->json(['message' => 'Overtime cancelled.', 'data' => $this->overtime->cancel($application_id, $request->user(), $request->validated('remarks'))]);
    }
}
