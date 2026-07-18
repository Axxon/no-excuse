<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApplicationResource;
use App\Jobs\ScoreApplication;
use App\Jobs\ScreenApplication;
use App\Jobs\SendCandidateDecision;
use App\Mail\CandidateDecisionMail;
use App\Models\Application;
use App\Models\JobOffer;
use App\Services\ApplicationRetention;
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
        $applications = $offer->applications()->with(['annotations.user', 'offer.organization'])
            ->orderByRaw('recruiter_rank is null')
            ->orderBy('recruiter_rank')
            ->orderByDesc('final_score')
            ->get();

        return ApplicationResource::collection($applications);
    }

    public function show(Request $request, Application $application): ApplicationResource
    {
        $this->authorizeApplication($request, $application);
        abort_unless($application->cv_path && Storage::disk('local')->exists($application->cv_path), 410, 'Ce CV a été supprimé conformément à la politique de rétention.');
        $this->markAsRead($request, $application);

        return new ApplicationResource($application->fresh()->load('annotations.user'));
    }

    public function cv(Request $request, Application $application)
    {
        $this->authorizeApplication($request, $application);
        abort_unless($application->cv_path && Storage::disk('local')->exists($application->cv_path), 410, 'Ce CV a été supprimé conformément à la politique de rétention.');
        $this->markAsRead($request, $application);

        return Storage::disk('local')->download($application->cv_path, $application->cv_original_name);
    }

    public function decisionPreview(Request $request, Application $application)
    {
        $this->authorizeApplication($request, $application);
        abort_unless($application->offer->organization?->is_demo, 404);
        abort_unless($application->notified_at && in_array($application->status, ['rejected_out_of_scope', 'rejected_final', 'selected'], true), 409, 'La décision candidat n’est pas encore disponible.');

        return response((new CandidateDecisionMail($application->load('offer.organization')))->render())
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'no-store');
    }

    public function dataExport(Request $request, Application $application): JsonResponse
    {
        $this->authorizeApplication($request, $application);
        $application->load(['annotations.user', 'events', 'offer']);

        return response()->json([
            'exported_at' => now()->toIso8601String(),
            'application' => (new ApplicationResource($application))->resolve($request),
            'events' => $application->events->map(fn ($event): array => [
                'type' => $event->type,
                'metadata' => $event->metadata,
                'created_at' => $event->created_at?->toIso8601String(),
            ]),
        ])->header('Cache-Control', 'no-store');
    }

    public function erasePersonalData(Request $request, Application $application, ApplicationRetention $retention): JsonResponse
    {
        $this->authorizeApplication($request, $application);
        $this->authorizeCanWrite($request);
        abort_if($application->offer->status !== 'selection_made', 409, 'La campagne doit être terminée avant cet effacement.');
        abort_if($application->personal_data_deleted_at, 409, 'Les données personnelles ont déjà été effacées.');
        $retention->anonymize($application);

        return response()->json([], 204);
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

        return new ApplicationResource($application->fresh()->load('annotations.user'));
    }

    public function screeningDecision(Request $request, Application $application): ApplicationResource
    {
        $this->authorizeApplication($request, $application);
        $this->authorizeCanWrite($request);
        $data = $request->validate(['decision' => ['required', 'in:reject,qualify']]);

        $dispatch = DB::transaction(function () use ($application, $request, $data): string {
            $locked = Application::query()->lockForUpdate()->findOrFail($application->id);
            abort_unless($locked->status === 'rejection_proposed', 409, 'Cette proposition a déjà été examinée.');
            $status = $data['decision'] === 'reject' ? 'rejected_out_of_scope' : 'qualified';
            $locked->update([
                'status' => $status,
                'screening_reviewed_by' => $request->user()->id,
                'screening_reviewed_at' => now(),
                'notification_state' => $status === 'rejected_out_of_scope' ? 'pending' : 'none',
            ]);
            $locked->events()->create([
                'type' => 'screening_reviewed',
                'user_id' => $request->user()->id,
                'metadata' => ['decision' => $data['decision']],
            ]);
            if ($status === 'rejected_out_of_scope') {
                $locked->events()->create(['type' => 'candidate_notification_queued', 'user_id' => $request->user()->id, 'metadata' => ['status' => $status]]);
            }

            return $status;
        });

        if ($dispatch === 'qualified') {
            ScoreApplication::dispatch($application->id)->onQueue('candidate-scoring');
        } else {
            SendCandidateDecision::dispatch($application->id)->onQueue('notifications');
        }

        return new ApplicationResource($application->fresh()->load(['annotations.user', 'offer.organization']));
    }

    public function retry(Request $request, Application $application): ApplicationResource
    {
        $this->authorizeApplication($request, $application);
        $this->authorizeCanWrite($request);

        $stage = DB::transaction(function () use ($application, $request): string {
            $locked = Application::query()->lockForUpdate()->findOrFail($application->id);
            abort_unless($locked->status === 'processing_failed' && in_array($locked->processing_stage, ['screening', 'scoring'], true), 409, 'Cette candidature ne nécessite pas de relance.');
            $stage = $locked->processing_stage;
            $locked->update([
                'status' => $stage === 'screening' ? 'received' : 'qualified',
                'processing_error' => null,
            ]);
            $locked->events()->create(['type' => 'processing_retried', 'user_id' => $request->user()->id, 'metadata' => ['stage' => $stage]]);

            return $stage;
        });

        ($stage === 'screening' ? ScreenApplication::dispatch($application->id) : ScoreApplication::dispatch($application->id))
            ->onQueue($stage === 'screening' ? 'candidate-intake' : 'candidate-scoring');

        return new ApplicationResource($application->fresh()->load(['annotations.user', 'offer.organization']));
    }

    public function retryNotification(Request $request, Application $application): ApplicationResource
    {
        $this->authorizeApplication($request, $application);
        $this->authorizeCanWrite($request);
        DB::transaction(function () use ($application, $request): void {
            $locked = Application::query()->lockForUpdate()->findOrFail($application->id);
            abort_unless(in_array($locked->notification_state, ['failed', 'attention_required'], true), 409, 'Cet e-mail ne nécessite pas de relance.');
            $locked->update([
                'notification_state' => 'pending', 'notification_error' => null,
                'notification_attempted_at' => null, 'notification_message_id' => null,
            ]);
            $locked->events()->create(['type' => 'candidate_notification_retried', 'user_id' => $request->user()->id, 'metadata' => null]);
        });
        SendCandidateDecision::dispatch($application->id)->onQueue('notifications');

        return new ApplicationResource($application->fresh()->load(['annotations.user', 'offer.organization']));
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
        $notifyIds = DB::transaction(function () use ($application, $request): array {
            $offer = JobOffer::query()->lockForUpdate()->findOrFail($application->job_offer_id);
            $selected = Application::query()->lockForUpdate()->findOrFail($application->id);
            abort_unless($offer->status === 'closed' && ! $offer->selected_application_id, 409, 'Une sélection finale existe déjà ou la campagne n’est pas prête.');
            abort_unless($selected->status === 'shortlisted', 409, 'Seule une candidature du top 10 peut être sélectionnée.');

            $selected->update(['status' => 'selected', 'selected_at' => now(), 'notification_state' => 'pending']);
            $selected->events()->create(['type' => 'selected', 'user_id' => $request->user()->id, 'metadata' => null]);
            $rejected = $offer->applications()
                ->whereKeyNot($selected->id)
                ->whereNotIn('status', ['rejected_out_of_scope'])
                ->lockForUpdate()
                ->get();
            $rejected->each(fn ($candidate) => $candidate->update(['status' => 'rejected_final', 'notification_state' => 'pending']));
            $offer->update(['status' => 'selection_made', 'selected_application_id' => $selected->id]);

            $selected->events()->create(['type' => 'candidate_notification_queued', 'user_id' => $request->user()->id, 'metadata' => ['status' => 'selected']]);
            $rejected->each(fn ($candidate) => $candidate->events()->create(['type' => 'candidate_notification_queued', 'user_id' => $request->user()->id, 'metadata' => ['status' => 'rejected_final']]));

            return [$selected->id, ...$rejected->modelKeys()];
        });

        collect($notifyIds)->each(fn ($id) => SendCandidateDecision::dispatch($id)->onQueue('notifications'));

        return new ApplicationResource($application->fresh()->load('annotations.user'));
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

    private function markAsRead(Request $request, Application $application): void
    {
        if (! $application->read_at) {
            $application->update(['read_at' => now()]);
        }
        $application->events()->create(['type' => 'read_by_recruiter', 'user_id' => $request->user()->id, 'metadata' => null]);
    }

    private function authorizeCanWrite(Request $request): void
    {
        abort_if($request->user()->role === 'viewer', 403, 'Ce compte est en lecture seule.');
    }
}
