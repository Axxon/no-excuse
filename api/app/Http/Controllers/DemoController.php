<?php

namespace App\Http\Controllers;

use App\Models\DemoWaitlistEntry;
use App\Services\DemoSandbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

class DemoController extends Controller
{
    public function status(DemoSandbox $sandbox): JsonResponse
    {
        $activeSessions = $sandbox->activeCount();
        $waiting = DemoWaitlistEntry::query()->where('status', 'waiting')->oldest();
        $waitlistCount = (clone $waiting)->count();
        $waitlist = $waiting->limit(20)->get()->values()->map(
            fn (DemoWaitlistEntry $entry, int $index): array => [
                'position' => $index + 1,
                'masked_email' => $entry->maskedEmail(),
            ],
        );

        return response()->json([
            'enabled' => (bool) config('no-excuse.public_demo.enabled'),
            'candidate_count' => 20,
            'lifetime_hours' => (int) config('no-excuse.public_demo.lifetime_hours'),
            'active_sessions' => $activeSessions,
            'max_sessions' => $sandbox->maxSessions(),
            'at_capacity' => $activeSessions >= $sandbox->maxSessions(),
            'waitlist_count' => $waitlistCount,
            'waitlist' => $waitlist,
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

    public function store(Request $request, DemoSandbox $sandbox): JsonResponse
    {
        abort_unless(config('no-excuse.public_demo.enabled'), 404);
        $visitorKey = $this->visitorRateLimitKey($request);
        $decaySeconds = max(1, (int) config('no-excuse.public_demo.lifetime_hours')) * 3600;
        if (RateLimiter::hit($visitorKey, $decaySeconds) > 1) {
            return response()->json([
                'message' => 'Une seule sandbox est disponible par visiteur pendant la durée de la démonstration.',
                'retry_after' => RateLimiter::availableIn($visitorKey),
            ], 429);
        }
        try {
            ['user' => $user, 'offer' => $offer] = $sandbox->create();
        } catch (RuntimeException $exception) {
            RateLimiter::clear($visitorKey);

            return response()->json(['message' => $exception->getMessage()], 503);
        }
        $payload = AuthController::payloadFor($user);
        $payload['demo'] = [
            'offer_uuid' => $offer->public_id,
            'expires_at' => $user->organization->expires_at?->toIso8601String(),
        ];

        return response()->json($payload, 201);
    }

    public function reset(Request $request): JsonResponse
    {
        abort_unless($request->user()->organization?->is_demo, 404);

        return response()->json(['message' => 'Cette sandbox a déjà été créée. Une seule sandbox est autorisée par visiteur.'], 429);
    }

    private function visitorRateLimitKey(Request $request): string
    {
        return 'no-excuse:demo-visitor:'.hash('sha256', $request->ip().'|'.($request->userAgent() ?? 'unknown'));
    }
}
