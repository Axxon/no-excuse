<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DemoSandbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::query()->where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Identifiants invalides.'], 422);
        }

        return response()->json(self::payloadFor($user));
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['user' => self::userPayload($user)]);
    }

    public function logout(Request $request, DemoSandbox $sandbox): JsonResponse
    {
        $user = $request->user();
        if ($user->organization?->is_demo) {
            $sandbox->destroy($user->organization);
        } else {
            $user->currentAccessToken()?->delete();
        }

        return response()->json([], 204);
    }

    public static function payloadFor(User $user): array
    {
        return [
            'token' => $user->createToken('web')->plainTextToken,
            'user' => self::userPayload($user),
        ];
    }

    private static function userPayload(User $user): array
    {
        return [
            'uuid' => $user->public_id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'organization' => $user->organization ? [
                'uuid' => $user->organization->public_id,
                'name' => $user->organization->name,
                'is_demo' => $user->organization->is_demo,
                'expires_at' => $user->organization->expires_at?->toIso8601String(),
            ] : null,
        ];
    }
}
