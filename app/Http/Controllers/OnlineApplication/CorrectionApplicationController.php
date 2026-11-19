<?php

namespace App\Http\Controllers\OnlineApplication;

use App\Http\Requests\OnlineApplication\ApplicationDecisionRequest;
use App\Http\Requests\OnlineApplication\CorrectionApplicationRequest;
use App\Services\OnlineApplication\CorrectionApplicationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CorrectionApplicationController extends Controller
{
    public function __construct(private CorrectionApplicationService $corrections) {}

    public function index(Request $request)
    {
        return response()->json([
            'message' => 'success',
            'data' => $this->corrections->findAll($request, $request->user()),
        ]);
    }

    public function find(Request $request, int $application_id)
    {
        return response()->json([
            'message' => 'success',
            'data' => $this->corrections->findById($application_id, $request->user()),
        ]);
    }

    public function store(CorrectionApplicationRequest $request)
    {
        return response()->json([
            'message' => 'Correction of logs application submitted.',
            'data' => $this->corrections->submit($request->validated(), $request->user()),
        ], 201);
    }

    public function approve(ApplicationDecisionRequest $request, int $application_id)
    {
        return response()->json([
            'message' => 'Correction of logs approval recorded.',
            'data' => $this->corrections->decide($application_id, $request->user(), 'approved', $request->validated('remarks')),
        ]);
    }

    public function disapprove(ApplicationDecisionRequest $request, int $application_id)
    {
        return response()->json([
            'message' => 'Correction of logs disapproved.',
            'data' => $this->corrections->decide($application_id, $request->user(), 'disapproved', $request->validated('remarks')),
        ]);
    }

    public function cancel(ApplicationDecisionRequest $request, int $application_id)
    {
        return response()->json([
            'message' => 'Correction of logs cancelled.',
            'data' => $this->corrections->cancel($application_id, $request->user(), $request->validated('remarks')),
        ]);
    }
}
