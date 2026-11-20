<?php

namespace App\Http\Controllers\Setup;

use App\Http\Requests\Setup\ClearanceRequirementRequest;
use App\Http\Requests\Setup\ClearanceSignatoryRequest;
use App\Http\Requests\Setup\ClearanceTypeRequest;
use App\Services\Setup\ClearanceSetupService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ClearanceSetupController extends Controller
{
    public function __construct(private ClearanceSetupService $clearanceService) {}

    // -------------------------------------------------------------------------
    // Clearance Types
    // -------------------------------------------------------------------------

    public function indexTypes(Request $request)
    {
        return response()->json([
            'message' => 'success',
            'data' => $this->clearanceService->searchTypes($request),
        ]);
    }

    public function findType(int $clearance_type_id)
    {
        $type = $this->clearanceService->findType($clearance_type_id);
        if (! $type) {
            return response()->json(['message' => 'Clearance type not found.'], 404);
        }

        return response()->json(['message' => 'success', 'data' => $type]);
    }

    public function storeType(ClearanceTypeRequest $request)
    {
        $type = $this->clearanceService->createType($request->validated());

        return response()->json([
            'message' => 'Clearance type created successfully.',
            'data' => $type,
        ], 201);
    }

    public function updateType(ClearanceTypeRequest $request, int $clearance_type_id)
    {
        $type = $this->clearanceService->updateType($clearance_type_id, $request->validated());

        return response()->json([
            'message' => 'Clearance type updated successfully.',
            'data' => $type,
        ]);
    }

    public function destroyType(int $clearance_type_id)
    {
        $this->clearanceService->deleteType($clearance_type_id);

        return response()->json(['message' => 'Clearance type deleted successfully.']);
    }

    // -------------------------------------------------------------------------
    // Clearance Requirements
    // -------------------------------------------------------------------------

    public function indexRequirements(Request $request)
    {
        return response()->json([
            'message' => 'success',
            'data' => $this->clearanceService->searchRequirements($request),
        ]);
    }

    public function findRequirement(int $requirement_id)
    {
        $requirement = $this->clearanceService->findRequirement($requirement_id);
        if (! $requirement) {
            return response()->json(['message' => 'Clearance requirement not found.'], 404);
        }

        return response()->json(['message' => 'success', 'data' => $requirement]);
    }

    public function storeRequirement(ClearanceRequirementRequest $request)
    {
        $requirement = $this->clearanceService->createRequirement($request->validated());

        return response()->json([
            'message' => 'Clearance requirement created successfully.',
            'data' => $requirement,
        ], 201);
    }

    public function updateRequirement(ClearanceRequirementRequest $request, int $requirement_id)
    {
        $requirement = $this->clearanceService->updateRequirement($requirement_id, $request->validated());

        return response()->json([
            'message' => 'Clearance requirement updated successfully.',
            'data' => $requirement,
        ]);
    }

    public function destroyRequirement(int $requirement_id)
    {
        $this->clearanceService->deleteRequirement($requirement_id);

        return response()->json(['message' => 'Clearance requirement deleted successfully.']);
    }

    // -------------------------------------------------------------------------
    // Clearance Signatories
    // -------------------------------------------------------------------------

    public function indexSignatories(Request $request)
    {
        return response()->json([
            'message' => 'success',
            'data' => $this->clearanceService->searchSignatories($request),
        ]);
    }

    public function findSignatory(int $signatory_id)
    {
        $signatory = $this->clearanceService->findSignatory($signatory_id);
        if (! $signatory) {
            return response()->json(['message' => 'Clearance signatory not found.'], 404);
        }

        return response()->json(['message' => 'success', 'data' => $signatory]);
    }

    public function storeSignatory(ClearanceSignatoryRequest $request)
    {
        $signatory = $this->clearanceService->createSignatory($request->validated());

        return response()->json([
            'message' => 'Clearance signatory created successfully.',
            'data' => $signatory,
        ], 201);
    }

    public function updateSignatory(ClearanceSignatoryRequest $request, int $signatory_id)
    {
        $signatory = $this->clearanceService->updateSignatory($signatory_id, $request->validated());

        return response()->json([
            'message' => 'Clearance signatory updated successfully.',
            'data' => $signatory,
        ]);
    }

    public function destroySignatory(int $signatory_id)
    {
        $this->clearanceService->deleteSignatory($signatory_id);

        return response()->json(['message' => 'Clearance signatory deleted successfully.']);
    }
}
