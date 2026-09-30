<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;


class AuthenticationController  extends Controller
{
    /**
     * Login
     *
     * @unauthenticated
     */
    public function store(LoginRequest $request)
    {
        $request->authenticate();
        $request->session()->regenerate();

        return response()->json(['message' => 'Authenticated successfully.']);
    }

    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->noContent();
    }
  /**
   * Get a dev token (local only)
   *
   * @unauthenticated
   */
    public function token(LoginRequest $request): JsonResponse
    {
        abort_unless(app()->environment('local'), 404);

        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        return response()->json([
            'token' => $user->createToken('scramble')->plainTextToken,
        ]);
    }
}