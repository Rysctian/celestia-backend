<?php

namespace App\Http\Controllers\OnlineApplication;

use App\Http\Requests\OnlineApplication\ApproverSetupRequest;
use App\Models\OnlineApplication\ApproverSetup;
use App\Services\OnlineApplication\ApproverSetupService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ApproverSetupController extends Controller
{
    public function __construct(private ApproverSetupService $setups) {}

    public function index(Request $request)
    {
        return response()->json(['message' => 'success', 'data' => $this->setups->findAll($request)]);
    }

    public function find(string $employee_id, string $application_type)
    {
        abort_unless(in_array($application_type, ApproverSetup::APPLICATION_TYPES, true), 404);

        return response()->json(['message' => 'success', 'data' => $this->setups->findSetup($employee_id, $application_type)]);
    }

    public function update(ApproverSetupRequest $request)
    {
        return response()->json(['message' => 'Approver setup saved.', 'data' => $this->setups->saveSetup($request->validated())]);
    }
}
