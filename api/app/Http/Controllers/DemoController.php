<?php

namespace App\Http\Controllers;

use App\Services\DemoSandbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class DemoController extends Controller
{
    public function status(): JsonResponse
    {
        return response()->json([
            'enabled' => (bool) config('no-excuse.public_demo.enabled'),
            'candidate_count' => 20,
            'lifetime_hours' => (int) config('no-excuse.public_demo.lifetime_hours'),
        ]);
    }

    public function store(DemoSandbox $sandbox): JsonResponse
    {
        abort_unless(config('no-excuse.public_demo.enabled'), 404);
        try {
            ['user' => $user, 'offer' => $offer] = $sandbox->create();
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }
        $payload = AuthController::payloadFor($user);
        $payload['demo'] = [
            'offer_uuid' => $offer->public_id,
            'expires_at' => $user->organization->expires_at?->toIso8601String(),
        ];

        return response()->json($payload, 201);
    }

    public function reset(Request $request, DemoSandbox $sandbox): JsonResponse
    {
        $offer = $sandbox->reset($request->user()->organization);

        return response()->json([
            'offer_uuid' => $offer->public_id,
            'expires_at' => $request->user()->organization->expires_at?->toIso8601String(),
        ]);
    }
}
