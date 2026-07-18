<?php

namespace App\Services;

use App\Jobs\ReplayDemoApplication;
use App\Models\JobOffer;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DemoSandbox
{
    private const PUBLIC_SESSION_HARD_LIMIT = 20;

    public function __construct(private readonly DemoCvPdf $cvPdf) {}

    /** @return array{user: User, offer: JobOffer} */
    public function create(): array
    {
        return Cache::lock('no-excuse:demo-session-capacity', 15)->block(3, function (): array {
            $this->pruneExpired();
            if ($this->activeCount() >= $this->maxSessions()) {
                throw new RuntimeException('Toutes les démonstrations sont occupées. Inscrivez-vous pour être prévenu dès qu’une place se libère.');
            }

            return $this->createSandbox();
        });
    }

    public function activeCount(): int
    {
        return Organization::query()->where('is_demo', true)->where('expires_at', '>', now())->count();
    }

    public function maxSessions(): int
    {
        return min(self::PUBLIC_SESSION_HARD_LIMIT, max(1, (int) config('no-excuse.public_demo.max_sessions')));
    }

    /** @return array{user: User, offer: JobOffer} */
    private function createSandbox(): array
    {

        return DB::transaction(function (): array {
            $suffix = Str::lower(Str::random(8));
            $organization = Organization::create([
                'name' => 'Atelier RH '.$suffix,
                'notification_sender_name' => 'Équipe recrutement — Démo',
                'notification_reply_to' => 'demo-'.$suffix.'@example.test',
                'default_screening_provider' => 'openai',
                'default_scoring_provider' => 'anthropic',
                'screening_workers' => 2,
                'scoring_workers' => 1,
                'screening_prompt' => config('no-excuse.prompts.screening'),
                'scoring_prompt' => config('no-excuse.prompts.scoring'),
                'is_demo' => true,
                'expires_at' => now()->addHours(config('no-excuse.public_demo.lifetime_hours')),
            ]);
            $user = $organization->users()->create([
                'name' => 'Camille, équipe RH',
                'email' => 'demo-'.$suffix.'@example.test',
                'password' => Str::password(32),
                'role' => 'owner',
            ]);

            return ['user' => $user, 'offer' => $this->seedOffer($user)];
        });
    }

    public function pruneExpired(): int
    {
        $organizations = Organization::query()
            ->where('is_demo', true)
            ->where('expires_at', '<=', now())
            ->get();
        $organizations->each(fn (Organization $organization) => $this->destroy($organization));

        return $organizations->count();
    }

    public function destroy(Organization $organization): void
    {
        DB::transaction(function () use ($organization): void {
            $organization->jobOffers()->each(function (JobOffer $offer): void {
                Storage::disk('local')->deleteDirectory('cvs/'.$offer->public_id);
                $offer->delete();
            });
            $organization->users()->each(function (User $user): void {
                $user->tokens()->delete();
                $user->delete();
            });
            $organization->delete();
        });
    }

    private function seedOffer(User $user): JobOffer
    {
        $offer = $user->jobOffers()->create([
            'organization_id' => $user->organization_id,
            'company' => 'Atelier Numérique',
            'title' => 'Développeur·se Laravel / Vue',
            'location' => 'Paris ou télétravail',
            'description' => 'Nous recherchons une personne capable de construire et maintenir un produit SaaS moderne avec Laravel, PHP, Vue, TypeScript, PostgreSQL, Redis, Docker et des tests automatisés.',
            'criteria' => ['Laravel', 'PHP', 'Vue', 'TypeScript', 'PostgreSQL', 'Redis', 'Docker', 'tests automatisés'],
            'rejection_message' => "Merci pour votre candidature. Après analyse, l'expérience présentée ne correspond pas suffisamment au périmètre professionnel de cette offre fictive.",
            'final_rejection_message' => 'Merci pour la qualité de cette candidature fictive. Un autre profil a été retenu dans cette démonstration.',
            'screening_provider' => 'openai',
            'screening_model' => 'simulation locale',
            'scoring_provider' => 'anthropic',
            'scoring_model' => 'simulation locale',
            'ingestion_key_hash' => hash('sha256', Str::random(64)),
            'status' => 'open',
            'opens_at' => now(),
            'closes_at' => $user->organization->expires_at,
        ]);

        $applicationIds = [];
        foreach ($this->candidates() as $index => $candidate) {
            $path = 'demo/cvs/cv-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'.pdf';
            if (! Storage::disk('local')->exists($path)) {
                Storage::disk('local')->put($path, $this->cvPdf->render($candidate, $index));
            }
            $application = $offer->applications()->create([
                'candidate_name' => $candidate['name'],
                'candidate_email' => 'candidat-'.($index + 1).'@example.test',
                'source' => ['linkedin', 'ats-partenaire', 'site-carriere'][$index % 3],
                'external_reference' => 'demo-'.$offer->public_id.'-'.($index + 1),
                'cv_path' => $path,
                'cv_original_name' => 'CV '.$candidate['name'].'.pdf',
                'cover_letter' => 'Candidature entièrement fictive créée pour découvrir no-excuse.',
                'status' => 'received',
                'status_token_hash' => hash('sha256', Str::random(64)),
            ]);
            $application->events()->create(['type' => 'received', 'metadata' => ['source' => $application->source, 'demo' => true]]);
            $applicationIds[] = $application->id;
        }

        $delay = max(0, (int) config('no-excuse.public_demo.processing_delay_seconds'));
        foreach ($applicationIds as $index => $applicationId) {
            ReplayDemoApplication::dispatch($applicationId, $index)
                ->delay(now()->addSeconds($index * $delay))
                ->afterCommit()
                ->onQueue('candidate-intake');
        }

        return $offer;
    }

    /** @return list<array{name: string, role: string, years: string, skills: string, summary: string}> */
    private function candidates(): array
    {
        return require resource_path('demo/candidates.php');
    }
}
