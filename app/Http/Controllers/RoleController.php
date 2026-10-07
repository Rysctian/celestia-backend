<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleAccessRequest;
use App\Http\Requests\RoleRequest;
use App\Models\Role;
use App\Services\MenuAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RoleController extends Controller
{
    public function __construct(private MenuAccessService $menuAccess) {}

    public function index(Request $request)
    {
        return response()->json(['message' => 'success', 'data' => Role::filterSearch($request)->get()]);
    }

    public function store(RoleRequest $request)
    {
        $role = Role::create($request->validated());

        return response()->json(['message' => 'Role created.', 'data' => $role], 201);
    }

    public function update(RoleRequest $request, Role $role)
    {
        $role->update($request->validated());

        return response()->json(['message' => 'Role updated.', 'data' => $role]);
    }

    public function access(Role $role)
    {
        return response()->json(['message' => 'success', 'data' => $this->menuAccess->grants($role)]);
    }

    public function updateAccess(RoleAccessRequest $request, Role $role): JsonResponse
    {
        $grants = $this->menuAccess->replaceGrants($role, $request->validated('access'));

        return response()->json(['message' => 'Role access updated.', 'data' => $grants]);
    }

    public function destroy(RoleRequest $request)
    {
        $role = Role::findOrFail($request->id);
        $role->delete();

        return response()->json(['message' => 'Role deleted successfully']);
    }
}
