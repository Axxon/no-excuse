<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApplicationResource;
use App\Jobs\SendCandidateDecision;
use App\Models\Application;
use App\Models\JobOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ApplicationController extends Controller
{
    public function index(Request $request, JobOffer $offer): AnonymousResourceCollection
    {
        $this->authorizeOffer($request, $offer);
        $applications = $offer->applications()->with('annotations')
            ->orderByRaw('recruiter_rank is null')
            ->orderBy('recruiter_rank')
            ->orderByDesc('final_score')
            ->get();

        return ApplicationResource::collection($applications);
    }

    public function show(Request $request, Application $application): ApplicationResource
    {
        $this->authorizeApplication($request, $application);
        $this->markAsRead($application);

        return new ApplicationResource($application->fresh()->load('annotations'));
    }

    public function cv(Request $request, Application $application)
    {
        $this->authorizeApplication($request, $application);
        $this->markAsRead($application);

        return Storage::disk('local')->download($application->cv_path, $application->cv_original_name);
    }

    public function annotate(Request $request, Application $application): JsonResponse
    {
        $this->authorizeApplication($request, $application);
        $this->authorizeCanWrite($request);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $annotation = $application->annotations()->make($data);
        $annotation->user()->associate($request->user());
        $annotation->save();

        return response()->json(['data' => ['uuid' => $annotation->public_id, 'body' => $annotation->body, 'created_at' => $annotation->created_at?->toIso8601String()]], 201);
    }

    public function feedback(Request $request, Application $application): ApplicationResource
    {
        $this->authorizeApplication($request, $application);
        $this->authorizeCanWrite($request);
        $data = $request->validate(['candidate_feedback' => ['nullable', 'string', 'max:3000']]);
        $application->update($data);

        return new ApplicationResource($application->fresh()->load('annotations'));
    }

    public function reorder(Request $request, JobOffer $offer): AnonymousResourceCollection
    {
        $this->authorizeOffer($request, $offer);
        $this->authorizeCanWrite($request);
        $data = $request->validate(['applications' => ['required', 'array', 'max:10'], 'applications.*' => ['required', 'uuid']]);
        $shortlisted = $offer->applications()->where('status', 'shortlisted')->whereIn('public_id', $data['applications'])->get()->keyBy('public_id');
        abort_unless($shortlisted->count() === count($data['applications']), 422, 'La liste contient une candidature non présélectionnée.');
        DB::transaction(fn () => collect($data['applications'])->each(fn ($uuid, $index) => $shortlisted[$uuid]->update(['recruiter_rank' => $index + 1])));

        return $this->index($request, $offer);
    }

    public function select(Request $request, Application $application): ApplicationResource
    {
        $this->authorizeApplication($request, $application);
        $this->authorizeCanWrite($request);
        $offer = $application->offer;
        abort_unless($offer->status === 'closed', 409, 'Clôturez la campagne avant la sélection finale.');
        abort_if($offer->applications()->whereIn('status', ['received', 'screening', 'qualified', 'scoring'])->exists(), 409, 'Des candidatures sont encore en cours de traitement.');

        $notifyIds = DB::transaction(function () use ($offer, $application): array {
            $application->update(['status' => 'selected', 'selected_at' => now()]);
            $application->events()->create(['type' => 'selected', 'metadata' => null]);
            $rejected = $offer->applications()
                ->whereKeyNot($application->id)
                ->whereNotIn('status', ['rejected_out_of_scope'])
                ->get();
            $rejected->each(fn ($candidate) => $candidate->update(['status' => 'rejected_final']));
            $offer->update(['status' => 'selection_made']);

            return [$application->id, ...$rejected->modelKeys()];
        });

        collect($notifyIds)->each(fn ($id) => SendCandidateDecision::dispatch($id)->onQueue('notifications'));

        return new ApplicationResource($application->fresh()->load('annotations'));
    }

    private function authorizeOffer(Request $request, JobOffer $offer): void
    {
        abort_unless($offer->organization_id === $request->user()->organization_id, 404);
    }

    private function authorizeApplication(Request $request, Application $application): void
    {
        $application->loadMissing('offer');
        $this->authorizeOffer($request, $application->offer);
    }

    private function markAsRead(Application $application): void
    {
        if ($application->read_at) {
            return;
        }

        $application->update(['read_at' => now()]);
        $application->events()->create(['type' => 'read_by_recruiter', 'metadata' => null]);
    }

    private function authorizeCanWrite(Request $request): void
    {
        abort_if($request->user()->role === 'viewer', 403, 'Ce compte est en lecture seule.');
    }
}
