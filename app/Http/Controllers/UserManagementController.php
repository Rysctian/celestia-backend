<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRoleRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with('role:id,code,name')->filterSearch($request)->get(['id', 'employee_id', 'name', 'email', 'role_id']);

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
