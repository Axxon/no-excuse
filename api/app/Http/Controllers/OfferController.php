<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfferResource;
use App\Models\JobOffer;
use App\Services\FinalizeOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OfferController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $offers = JobOffer::query()
            ->where('organization_id', $request->user()->organization_id)
            ->withCount([
                'applications',
                'applications as pending_count' => fn ($query) => $query->whereIn('status', ['received', 'screening', 'qualified', 'scoring']),
                'applications as shortlisted_count' => fn ($query) => $query->where('status', 'shortlisted'),
            ])
            ->latest()->get();

        return OfferResource::collection($offers);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeCanWrite($request);
        $this->authorizeNotDemo($request);
        $key = Str::random(64);
        $defaults = $request->user()->organization;
        $validated = $this->validated($request);
        $offer = $request->user()->jobOffers()->create([
            ...$validated,
            'organization_id' => $request->user()->organization_id,
            'screening_provider' => $validated['screening_provider'] ?? $defaults->default_screening_provider,
            'screening_model' => $validated['screening_model'] ?? $defaults->default_screening_model,
            'scoring_provider' => $validated['scoring_provider'] ?? $defaults->default_scoring_provider,
            'scoring_model' => $validated['scoring_model'] ?? $defaults->default_scoring_model,
            'ingestion_key_hash' => hash('sha256', $key),
        ]);

        return (new OfferResource($offer))
            ->additional(['meta' => ['ingestion_key' => $key]])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, JobOffer $offer): OfferResource
    {
        $this->authorizeOwner($request, $offer);

        return new OfferResource($offer->loadCount('applications'));
    }

    public function update(Request $request, JobOffer $offer): OfferResource
    {
        $this->authorizeOwner($request, $offer);
        $this->authorizeCanWrite($request);
        $this->authorizeNotDemo($request);
        abort_if($offer->status === 'selection_made', 409, 'Cette campagne est terminée.');
        $offer->update($this->validated($request));

        return new OfferResource($offer->fresh());
    }

    public function open(Request $request, JobOffer $offer): OfferResource
    {
        $this->authorizeOwner($request, $offer);
        $this->authorizeCanWrite($request);
        $this->authorizeNotDemo($request);
        $request->validate(['closes_at' => ['required', 'date', 'after:now']]);
        $offer->update(['status' => 'open', 'opens_at' => now(), 'closes_at' => $request->date('closes_at')]);

        return new OfferResource($offer->fresh());
    }

    public function close(Request $request, JobOffer $offer, FinalizeOffer $finalize): OfferResource
    {
        $this->authorizeOwner($request, $offer);
        $this->authorizeCanWrite($request);
        abort_if(
            $offer->applications()->whereIn('status', ['received', 'screening', 'qualified', 'scoring'])->exists(),
            409,
            'Attendez la fin des analyses avant de produire le top 10.',
        );
        $finalize->handle($offer);

        return new OfferResource($offer->fresh()->loadCount('applications'));
    }

    public function rotateIngestionKey(Request $request, JobOffer $offer): JsonResponse
    {
        $this->authorizeOwner($request, $offer);
        $this->authorizeCanWrite($request);
        $this->authorizeNotDemo($request);
        abort_if($offer->status === 'selection_made', 409, 'Cette campagne est terminée.');

        $key = Str::random(64);
        $offer->update(['ingestion_key_hash' => hash('sha256', $key)]);

        return response()->json([
            'ingestion_key' => $key,
            'intake_url' => url('/api/v1/intake/'.$offer->public_id.'/applications'),
            'message' => 'La précédente clé est révoquée. Cette nouvelle clé ne sera affichée qu’une fois.',
        ]);
    }

    private function validated(Request $request): array
    {
        $providers = array_keys(config('no-excuse.ai.providers'));

        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'company' => ['required', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:160'],
            'description' => ['required', 'string', 'min:50'],
            'criteria' => ['required', 'array', 'min:1', 'max:20'],
            'criteria.*' => ['required', 'string', 'max:180'],
            'rejection_message' => ['required', 'string', 'min:20', 'max:3000'],
            'final_rejection_message' => ['required', 'string', 'min:20', 'max:3000'],
            'screening_provider' => ['sometimes', Rule::in($providers)],
            'screening_model' => ['nullable', 'string', 'max:160'],
            'scoring_provider' => ['sometimes', Rule::in($providers)],
            'scoring_model' => ['nullable', 'string', 'max:160'],
        ]);
    }

    private function authorizeOwner(Request $request, JobOffer $offer): void
    {
        abort_unless($offer->organization_id === $request->user()->organization_id, 404);
    }

    private function authorizeCanWrite(Request $request): void
    {
        abort_if($request->user()->role === 'viewer', 403, 'Ce compte est en lecture seule.');
    }

    private function authorizeNotDemo(Request $request): void
    {
        abort_if($request->user()->organization->is_demo, 403, 'La sandbox utilise uniquement sa campagne fictive préchargée.');
    }
}
