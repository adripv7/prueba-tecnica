<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $email = Str::lower($validated['email']);
        $limiterKey = 'login:'.Str::transliterate($email).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            $seconds = RateLimiter::availableIn($limiterKey);

            return response()->json([
                'message' => 'Demasiados intentos. Intenta de nuevo en un minuto.',
                'retry_after_seconds' => $seconds,
            ], 429)->header('Retry-After', (string) $seconds);
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($limiterKey, 60);

            return response()->json([
                'message' => 'El correo o la contraseña son incorrectos.',
            ], 401);
        }

        RateLimiter::clear($limiterKey);

        $token = $user->createToken($validated['device_name'] ?? 'react-app');

        return response()->json([
            'message' => 'Inicio de sesión correcto.',
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'permissions' => $user->permissionSlugs(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'role' => $request->user()->role,
                'permissions' => $request->user()->permissionSlugs(),
            ],
        ]);
    }
}
