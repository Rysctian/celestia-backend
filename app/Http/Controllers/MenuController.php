<?php

namespace App\Http\Controllers;

use App\Services\MenuAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MenuController extends Controller
{
    public function __construct(private MenuAccessService $menuAccess) {}

    public function mine(Request $request): JsonResponse
    {
        return response()->json(['message' => 'success', 'data' => $this->menuAccess->treeFor($request->user())]);
    }

    public function index(): JsonResponse
    {
        return response()->json(['message' => 'success', 'data' => $this->menuAccess->catalog()]);
    }
}
