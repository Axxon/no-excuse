<?php

namespace App\Http\Controllers;

use App\Jobs\ScreenApplication;
use App\Models\JobOffer;
use App\Services\CvPdfValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class IntegrationIntakeController extends Controller
{
    public function __invoke(Request $request, JobOffer $offer, CvPdfValidator $pdfValidator): JsonResponse
    {
        $offer->loadMissing('organization');
        abort_if($offer->organization?->is_demo, 403, 'La démonstration accepte uniquement ses CV fictifs préchargés.');
        $providedKey = $request->bearerToken();
        abort_unless(
            is_string($providedKey)
            && is_string($offer->ingestion_key_hash)
            && hash_equals($offer->ingestion_key_hash, hash('sha256', $providedKey)),
            404,
        );
        abort_unless(
            $offer->status === 'open' && $offer->closes_at?->isFuture(),
            409,
            'Cette campagne ne reçoit plus de candidatures.',
        );

        $data = $request->validate([
            'source' => ['required', 'string', 'max:80'],
            'external_reference' => ['required', 'string', 'max:190'],
            'candidate_name' => ['required', 'string', 'max:160'],
            'candidate_email' => ['required', 'email', 'max:255'],
            'cover_letter' => ['nullable', 'string', 'max:10000'],
            'cv' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf,application/x-pdf', 'max:10240'],
        ]);
        $data['candidate_email'] = Str::lower($data['candidate_email']);
        $pdfValidator->validate($request->file('cv'));

        $existing = $offer->applications()
            ->where('source', $data['source'])
            ->where('external_reference', $data['external_reference'])
            ->first();

        if ($existing) {
            return response()->json([
                'application_reference' => $existing->public_id,
                'status' => $existing->status,
                'duplicate' => true,
            ]);
        }

        $file = $request->file('cv');
        $path = $file->store('cvs/'.$offer->public_id, 'local');
        if (! is_string($path)) {
            throw new RuntimeException('Le stockage du CV a échoué.');
        }
        try {
            $application = DB::transaction(function () use ($offer, $data, $file, $path) {
                JobOffer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();
                $duplicate = $offer->applications()
                    ->where('source', $data['source'])
                    ->where('external_reference', $data['external_reference'])
                    ->first();
                if ($duplicate) {
                    return $duplicate;
                }
                $application = $offer->applications()->create([
                    'source' => $data['source'],
                    'external_reference' => $data['external_reference'],
                    'candidate_name' => $data['candidate_name'],
                    'candidate_email' => $data['candidate_email'],
                    'cover_letter' => $data['cover_letter'] ?? null,
                    'cv_path' => $path,
                    'cv_original_name' => $file->getClientOriginalName(),
                    'status_token_hash' => hash('sha256', Str::random(64)),
                ]);
                $application->events()->create([
                    'type' => 'received',
                    'metadata' => ['source' => $data['source']],
                ]);

                return $application;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        if ($application->cv_path !== $path) {
            Storage::disk('local')->delete($path);

            return response()->json([
                'application_reference' => $application->public_id,
                'status' => $application->status,
                'duplicate' => true,
            ]);
        }

        ScreenApplication::dispatch($application->id)->onQueue('candidate-intake');

        return response()->json([
            'application_reference' => $application->public_id,
            'status' => 'accepted',
            'duplicate' => false,
        ], 202);
    }
}
