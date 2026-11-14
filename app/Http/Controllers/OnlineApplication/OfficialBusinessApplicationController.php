<?php

namespace App\Http\Controllers\OnlineApplication;

use App\Http\Requests\OnlineApplication\ApplicationDecisionRequest;
use App\Http\Requests\OnlineApplication\OfficialBusinessApplicationRequest;
use App\Services\OnlineApplication\OfficialBusinessApplicationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OfficialBusinessApplicationController extends Controller
{
    public function __construct(private OfficialBusinessApplicationService $officialBusiness) {}

    public function index(Request $request)
    {
        return response()->json([
            'message' => 'success',
            'data' => $this->officialBusiness->findAll($request, $request->user()),
        ]);
    }

    public function find(Request $request, int $application_id)
    {
        return response()->json([
            'message' => 'success',
            'data' => $this->officialBusiness->findById($application_id, $request->user()),
        ]);
    }

    public function store(OfficialBusinessApplicationRequest $request)
    {
        return response()->json([
            'message' => 'Official business application submitted.',
            'data' => $this->officialBusiness->submit($request->validated(), $request->user()),
        ], 201);
    }

    public function approve(ApplicationDecisionRequest $request, int $application_id)
    {
        return response()->json([
            'message' => 'Official business approval recorded.',
            'data' => $this->officialBusiness->decide($application_id, $request->user(), 'approved', $request->validated('remarks')),
        ]);
    }

    public function disapprove(ApplicationDecisionRequest $request, int $application_id)
    {
        return response()->json([
            'message' => 'Official business disapproved.',
            'data' => $this->officialBusiness->decide($application_id, $request->user(), 'disapproved', $request->validated('remarks')),
        ]);
    }

    public function cancel(ApplicationDecisionRequest $request, int $application_id)
    {
        return response()->json([
            'message' => 'Official business cancelled.',
            'data' => $this->officialBusiness->cancel($application_id, $request->user(), $request->validated('remarks')),
        ]);
    }
}
