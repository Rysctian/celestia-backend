<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRoleRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class UserManagementController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::with('role:id,code,name')->orderBy('id')->get(['id', 'employee_id', 'name', 'email', 'role_id']);

        return response()->json(['message' => 'success', 'data' => $users]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(['message' => 'success', 'data' => $user->load('role:id,code,name')
            ->only(['id', 'employee_id', 'name', 'email', 'role_id', 'role'])]);
    }

    public function updateRole(UserRoleRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());

        return $this->show($user);
    }
}
