<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class AuthenticationController extends Controller
{
    /**
     * Login
     *
     * @unauthenticated
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        return response()->json(['message' => 'Authenticated successfully.']);
    }


    public function destroy(Request $request): Response
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        } elseif ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

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

        $user = $request->authenticate(withSession: false);
        $expiresAt = now()->addHour();

        return response()->json([
            'token' => $user->createToken('scramble', ['*'], $expiresAt)->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
  