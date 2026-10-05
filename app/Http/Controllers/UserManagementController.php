<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRoleRequest;
use App\Models\User;
use Illuminate\Routing\Controller;

class UserManagementController extends Controller
{
    public function index()
    {
        $users = User::with('role:id,code,name')->orderBy('id')->get(['id', 'employee_id', 'name', 'email', 'role_id']);

        return response()->json(['message' => 'success', 'data' => $users]);
    }

    public function show(User $user)
    {
        return response()->json(['message' => 'success', 'data' => $user->load('role:id,code,name')
            ->only(['id', 'employee_id', 'name', 'email', 'role_id', 'role'])]);
    }

    public function updateRole(UserRoleRequest $request, User $user)
    {
        $user->update($request->validated());

        return $this->show($user);
    }
}
