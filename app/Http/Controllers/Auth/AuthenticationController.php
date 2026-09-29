<?php

namespace App\Http\Controllers\Authentication;

use App\Models\User;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class AuthenticationController  extends Controller
{
 
    public function store(LoginRequest $request): JsonResponse
    {
       $request->authenticate();
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Authenticated successfully.',
        ]);
    }

    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->noContent();
    }
}