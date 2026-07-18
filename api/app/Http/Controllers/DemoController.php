<?php

namespace App\Http\Controllers;

use App\Models\DemoWaitlistEntry;
use App\Services\DemoSandbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use RuntimeException;

class DemoController extends Controller
{
    public function status(Request $request, DemoSandbox $sandbox): JsonResponse
    {
        $activeSessions = $sandbox->activeCount();
        $waiting = DemoWaitlistEntry::query()->where('status', 'waiting')->oldest();
        $waitlistCount = (clone $waiting)->count();
        $waitlist = $waiting->limit(20)->get()->values()->map(
            fn (DemoWaitlistEntry $entry, int $index): array => [
                'position' => $index + 1,
                'reference' => $entry->public_id,
            ],
        );

        return response()->json([
            'enabled' => (bool) config('no-excuse.public_demo.enabled'),
            'candidate_count' => 20,
            'lifetime_hours' => (int) config('no-excuse.public_demo.lifetime_hours'),
            'active_sessions' => $activeSessions,
            'max_sessions' => $sandbox->maxSessions(),
            'at_capacity' => $activeSessions + $sandbox->reservedCount() >= $sandbox->maxSessions(),
            'waitlist_count' => $waitlistCount,
            'waitlist' => $waitlist,
            'visitor_reference' => $this->visitorReference($request),
        ]);
    }

    public function waitlist(Request $request, DemoSandbox $sandbox): JsonResponse
    {
        abort_unless(config('no-excuse.public_demo.enabled'), 404);
        abort_unless($sandbox->activeCount() + $sandbox->reservedCount() >= $sandbox->maxSessions(), 409, 'Une place est disponible : lancez directement la démonstration.');
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:254'],
            'locale' => ['sometimes', 'in:fr,en'],
        ]);
        $normalized = mb_strtolower(trim($data['email']));
        $entry = DemoWaitlistEntry::query()->updateOrCreate(
            ['email_hash' => hash_hmac('sha256', $normalized, (string) config('app.key'))],
            ['email' => $normalized, 'locale' => $data['locale'] ?? 'fr', 'status' => 'waiting', 'notified_at' => null],
        );

        return response()->json([
            'message' => 'Inscription enregistrée. Un seul e-mail sera envoyé lorsqu’une place se libérera.',
            'reference' => $entry->public_id,
        ], 202);
    }

    public function store(Request $request, DemoSandbox $sandbox): JsonResponse
    {
        abort_unless(config('no-excuse.public_demo.enabled'), 404);
        $data = $request->validate(['access_token' => ['sometimes', 'string', 'size:64']]);
        $reservation = isset($data['access_token'])
            ? DemoWaitlistEntry::query()->where('access_token_hash', hash('sha256', $data['access_token']))->first()
            : null;
        abort_if(isset($data['access_token']) && ! $reservation, 403, 'Cette réservation de démonstration est invalide ou expirée.');
        $visitorKey = $this->visitorRateLimitKey($request);
        $decaySeconds = max(1, (int) config('no-excuse.public_demo.lifetime_hours')) * 3600;
        if (RateLimiter::hit($visitorKey, $decaySeconds) > 1) {
            return response()->json([
                'message' => 'Une seule sandbox est disponible par visiteur pendant la durée de la démonstration.',
                'retry_after' => RateLimiter::availableIn($visitorKey),
            ], 429);
        }
        try {
            ['user' => $user, 'offer' => $offer] = $sandbox->create($reservation);
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
        $reference = $request->header('X-Demo-Visitor');
        $identity = is_string($reference) && Str::isUuid($reference)
            ? $reference
            : $request->ip().'|'.($request->userAgent() ?? 'unknown');

        return 'no-excuse:demo-visitor:'.hash('sha256', $identity);
    }

    private function visitorReference(Request $request): string
    {
        $reference = $request->header('X-Demo-Visitor');

        return is_string($reference) && Str::isUuid($reference)
            ? $reference
            : (string) Str::uuid7();
    }
}
