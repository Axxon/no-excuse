<?php

namespace App\Http\Controllers;

use App\Models\DemoWaitlistEntry;
use App\Services\DemoSandbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class DemoController extends Controller
{
    public function status(DemoSandbox $sandbox): JsonResponse
    {
        return response()->json([
            'enabled' => (bool) config('no-excuse.public_demo.enabled'),
            'candidate_count' => 20,
            'lifetime_hours' => (int) config('no-excuse.public_demo.lifetime_hours'),
            'at_capacity' => $sandbox->activeCount() >= $sandbox->maxSessions(),
        ]);
    }

    public function waitlist(Request $request): JsonResponse
    {
        abort_unless(config('no-excuse.public_demo.enabled'), 404);
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:254'],
            'locale' => ['sometimes', 'in:fr,en'],
        ]);
        $normalized = mb_strtolower(trim($data['email']));
        DemoWaitlistEntry::query()->updateOrCreate(
            ['email_hash' => hash('sha256', $normalized)],
            ['email' => $normalized, 'locale' => $data['locale'] ?? 'fr', 'status' => 'waiting', 'notified_at' => null],
        );

        return response()->json(['message' => 'Inscription enregistrée. Un seul e-mail sera envoyé lorsqu’une place se libérera.'], 202);
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
