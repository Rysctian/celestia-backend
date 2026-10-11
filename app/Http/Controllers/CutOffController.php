<?php

namespace App\Http\Controllers;

use App\Http\Requests\CutOffRequest;
use App\Services\CutOffService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CutOffController extends Controller
{
    public function __construct(private CutOffService $cutOffService) {}

    public function index(Request $request)
    {
        return response()->json(['message' => 'success', 'data' => $this->cutOffService->search($request)]);
    }

    public function find(string $cut_off_id)
    {
        $cutOff = $this->cutOffService->findById($cut_off_id);
        if (! $cutOff) {
            return response()->json(['message' => 'Cut-off not found.'], 404);
        }

        return response()->json(['message' => 'success', 'data' => $cutOff]);
    }

    public function store(CutOffRequest $request)
    {
        $cutOff = $this->cutOffService->createCutOff($request->validated());

        return response()->json(['message' => 'Cut-off created successfully.', 'data' => $cutOff], 201);
    }

    public function update(CutOffRequest $request, string $cut_off_id)
    {
        $cutOff = $this->cutOffService->updateCutOff($request->validated(), $cut_off_id);

        return response()->json(['message' => 'Cut-off updated successfully.', 'data' => $cutOff]);
    }

    public function destroy(string $cut_off_id)
    {
        $this->cutOffService->deleteCutOff($cut_off_id);

        return response()->json(['message' => 'Cut-off deleted successfully.']);
    }
}
