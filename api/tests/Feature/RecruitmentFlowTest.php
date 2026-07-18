<?php

namespace Tests\Feature;

use App\Contracts\CandidateAnalyzer;
use App\Data\ScoringResult;
use App\Data\ScreeningResult;
use App\Jobs\ScoreApplication;
use App\Jobs\ScreenApplication;
use App\Jobs\SendCandidateDecision;
use App\Models\Application;
use App\Models\JobOffer;
use App\Models\User;
use App\Services\CvTextExtractor;
use App\Services\DemoCvPdf;
use App\Services\FinalizeOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecruitmentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_recruiter_can_install_the_company_and_configure_two_ai_providers(): void
    {
        $auth = $this->postJson('/api/setup', [
            'company_name' => 'Acme France',
            'name' => 'Alice RH',
            'email' => 'alice@example.test',
            'password' => 'Strong-password-2026',
            'password_confirmation' => 'Strong-password-2026',
        ])->assertCreated()->json();

        $this->postJson('/api/setup', [
            'company_name' => 'Intrus',
            'name' => 'Autre personne',
            'email' => 'other@example.test',
            'password' => 'Strong-password-2026',
            'password_confirmation' => 'Strong-password-2026',
        ])->assertConflict();

        $this->withToken($auth['token'])->postJson('/api/offers', $this->offerPayload([
            'screening_provider' => 'openai',
            'screening_model' => 'small-cost-model',
            'scoring_provider' => 'anthropic',
            'scoring_model' => 'fine-model',
        ]))->assertCreated()
            ->assertJsonPath('data.screening_provider', 'openai')
            ->assertJsonPath('data.scoring_provider', 'anthropic');
    }

    public function test_company_team_shares_offers_and_can_configure_velocity_and_prompts(): void
    {
        $owner = User::factory()->create();
        $teammate = $owner->organization->users()->create([
            'name' => 'Collègue RH',
            'email' => 'colleague@example.test',
            'password' => 'Strong-password-2026',
            'role' => 'recruiter',
        ]);
        $viewer = $owner->organization->users()->create([
            'name' => 'Direction',
            'email' => 'viewer@example.test',
            'password' => 'Strong-password-2026',
            'role' => 'viewer',
        ]);
        $offer = $this->offer($owner);

        Sanctum::actingAs($teammate);
        $this->getJson('/api/offers')->assertOk()->assertJsonPath('data.0.uuid', $offer->public_id);

        Sanctum::actingAs($viewer);
        $this->postJson('/api/offers', $this->offerPayload())->assertForbidden();

        Sanctum::actingAs($owner);
        $screeningPrompt = 'Filtre de base personnalisé qui conserve les compétences transférables et ignore toutes les données sensibles du candidat.';
        $scoringPrompt = 'Compare chaque critère professionnel avec des preuves observables, explique les écarts et ne prend jamais la décision finale.';
        $this->putJson('/api/organization', [
            'name' => 'Entreprise partagée',
            'notification_sender_name' => 'Équipe RH',
            'notification_reply_to' => 'rh@example.test',
            'default_screening_provider' => 'openai',
            'default_screening_model' => 'small-model',
            'default_scoring_provider' => 'anthropic',
            'default_scoring_model' => 'fine-model',
            'screening_workers' => 4,
            'scoring_workers' => 2,
            'screening_prompt' => $screeningPrompt,
            'scoring_prompt' => $scoringPrompt,
        ])->assertOk()
            ->assertJsonPath('data.screening_workers', 4)
            ->assertJsonPath('data.scoring_workers', 2)
            ->assertJsonPath('data.screening_prompt', $screeningPrompt)
            ->assertJsonPath('data.scoring_prompt', $scoringPrompt);
    }

    public function test_ai_provider_status_is_private_and_never_exposes_credentials(): void
    {
        config()->set('no-excuse.ai.mode', 'live');
        config()->set('no-excuse.ai.credentials.openai', true);
        config()->set('ai.providers.openai.key', 'super-secret-provider-token');

        $this->getJson('/api/meta/ai-providers')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $response = $this->getJson('/api/meta/ai-providers')
            ->assertOk()
            ->assertJsonPath('mode', 'live')
            ->assertJsonPath('providers.0.key', 'openai')
            ->assertJsonPath('providers.0.configured', true)
            ->assertJsonPath('providers.0.credential_configured', true);

        $this->assertStringNotContainsString('super-secret-provider-token', $response->getContent());
    }

    public function test_external_service_can_submit_a_cv_with_the_private_offer_key(): void
    {
        Queue::fake();
        Storage::fake('local');
        $offer = $this->offer();

        $this->getJson('/api/public/offers')->assertNotFound();
        $this->getJson('/api/offers/'.$offer->public_id)->assertUnauthorized();

        $payload = [
            'source' => 'linkedin',
            'external_reference' => 'linkedin-application-42',
            'candidate_name' => 'Bob Candidat',
            'candidate_email' => 'bob@example.test',
            'cover_letter' => 'Je souhaite rejoindre votre équipe.',
            'cv' => $this->uploadedCv(),
        ];
        $this->withToken('wrong-key')->post('/api/v1/intake/'.$offer->public_id.'/applications', $payload)->assertNotFound();

        $payload['cv'] = $this->uploadedCv();
        $response = $this->withToken('test-ingestion-key')
            ->post('/api/v1/intake/'.$offer->public_id.'/applications', $payload)
            ->assertAccepted()
            ->assertJsonStructure(['application_reference', 'status', 'duplicate']);

        $application = Application::where('public_id', $response->json('application_reference'))->firstOrFail();
        $this->assertSame('linkedin', $application->source);
        Storage::disk('local')->assertExists($application->cv_path);
        Queue::assertPushed(ScreenApplication::class, fn ($job) => $job->applicationId === $application->id);

        $payload['cv'] = $this->uploadedCv();
        $this->withToken('test-ingestion-key')
            ->post('/api/v1/intake/'.$offer->public_id.'/applications', $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('application_reference', $application->public_id);

        $payload['external_reference'] = 'linkedin-invalid-text-cv';
        $payload['cv'] = UploadedFile::fake()->createWithContent('cv.txt', 'Un CV au format texte ne doit plus être accepté.');
        $this->withToken('test-ingestion-key')
            ->post('/api/v1/intake/'.$offer->public_id.'/applications', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cv');
    }

    public function test_two_stage_pipeline_scores_a_qualified_application(): void
    {
        Queue::fake();
        Storage::fake('local');
        $application = $this->application();
        Storage::disk('local')->put($application->cv_path, app(DemoCvPdf::class)->render($this->candidateData(), 0));
        $this->app->bind(CandidateAnalyzer::class, fn () => new class implements CandidateAnalyzer
        {
            public function screen(JobOffer $offer, string $cvText): ScreeningResult
            {
                return new ScreeningResult(true, 82.5, 'Compétences principales présentes.');
            }

            public function score(JobOffer $offer, string $cvText): ScoringResult
            {
                return new ScoringResult(88.4, ['adéquation' => 90.0, 'expérience' => 84.0], 'Profil solide et pertinent.');
            }
        });

        (new ScreenApplication($application->id))->handle(app(CandidateAnalyzer::class), app(CvTextExtractor::class));
        $this->assertSame('qualified', $application->fresh()->status);
        Queue::assertPushed(ScoreApplication::class);

        (new ScoreApplication($application->id))->handle(app(CandidateAnalyzer::class), app(CvTextExtractor::class));
        $application->refresh();
        $this->assertSame('scored', $application->status);
        $this->assertSame(88.4, $application->final_score);
        $this->assertSame('Profil solide et pertinent.', $application->ai_summary);
    }

    public function test_out_of_scope_screening_requires_human_confirmation_before_email(): void
    {
        Queue::fake();
        Storage::fake('local');
        $application = $this->application();
        Storage::disk('local')->put($application->cv_path, app(DemoCvPdf::class)->render($this->candidateData(), 0));
        $reason = 'Les expériences présentées ne couvrent pas les compétences Laravel et Vue demandées.';
        $this->app->bind(CandidateAnalyzer::class, fn () => new class($reason) implements CandidateAnalyzer
        {
            public function __construct(private readonly string $reason) {}

            public function screen(JobOffer $offer, string $cvText): ScreeningResult
            {
                return new ScreeningResult(false, 24.0, $this->reason);
            }

            public function score(JobOffer $offer, string $cvText): ScoringResult
            {
                throw new \LogicException('Une candidature hors périmètre ne doit pas être scorée.');
            }
        });

        (new ScreenApplication($application->id))->handle(app(CandidateAnalyzer::class), app(CvTextExtractor::class));

        $application->refresh();
        $this->assertSame('rejection_proposed', $application->status);
        $this->assertSame($reason, $application->scope_reason);
        Queue::assertNotPushed(SendCandidateDecision::class);

        Sanctum::actingAs($application->offer->recruiter);
        $this->postJson('/api/applications/'.$application->public_id.'/screening-decision', ['decision' => 'reject'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected_out_of_scope');
        Queue::assertPushed(SendCandidateDecision::class, fn (SendCandidateDecision $job): bool => $job->applicationId === $application->id && $job->queue === 'notifications');
        $this->assertDatabaseHas('application_events', ['application_id' => $application->id, 'type' => 'candidate_notification_queued']);

        $this->getJson('/api/offers/'.$application->offer->public_id.'/applications')
            ->assertOk()
            ->assertJsonFragment([
                'uuid' => $application->public_id,
                'scope_reason' => $reason,
                'notification_status' => 'pending',
            ]);
    }

    public function test_closure_waits_for_all_reviews_and_scores_before_building_top_ten(): void
    {
        $offer = $this->offer();
        $this->application($offer, ['status' => 'rejection_proposed']);
        Sanctum::actingAs($offer->recruiter);

        $this->postJson('/api/offers/'.$offer->public_id.'/close')->assertOk()->assertJsonPath('data.status', 'closing');
        $this->assertSame(0, $offer->applications()->where('status', 'shortlisted')->count());

        $offer->applications()->update(['status' => 'scored', 'final_score' => 80]);
        app(FinalizeOffer::class)->request($offer->fresh());

        $this->assertSame('closed', $offer->fresh()->status);
        $this->assertSame(1, $offer->applications()->where('status', 'shortlisted')->count());
    }

    public function test_failed_processing_can_be_retried_only_at_its_recorded_stage(): void
    {
        Queue::fake();
        $application = $this->application(attributes: ['status' => 'processing_failed', 'processing_stage' => 'scoring', 'processing_error' => 'timeout']);
        Sanctum::actingAs($application->offer->recruiter);

        $this->postJson('/api/applications/'.$application->public_id.'/retry')->assertOk()->assertJsonPath('data.status', 'qualified');
        Queue::assertPushed(ScoreApplication::class, fn (ScoreApplication $job): bool => $job->applicationId === $application->id);
    }

    public function test_offer_closure_builds_a_top_ten_and_recruiter_selects_a_candidate(): void
    {
        Queue::fake();
        $offer = $this->offer();
        $applications = collect(range(1, 12))->map(fn ($index) => $this->application($offer, [
            'candidate_email' => "candidate{$index}@example.test",
            'final_score' => 100 - $index,
            'status' => 'scored',
        ]));

        app(FinalizeOffer::class)->handle($offer);
        $this->assertSame(10, $offer->applications()->where('status', 'shortlisted')->count());
        $this->assertSame(1, $applications->first()->fresh()->recruiter_rank);

        Sanctum::actingAs($offer->recruiter);
        $selected = $applications->first();
        $this->postJson('/api/applications/'.$selected->public_id.'/select')->assertOk()->assertJsonPath('data.status', 'selected');
        $this->assertSame('selection_made', $offer->fresh()->status);
        $this->assertSame($selected->id, $offer->fresh()->selected_application_id);
        $this->assertSame('rejected_final', $applications->last()->fresh()->status);
        Queue::assertPushed(SendCandidateDecision::class, 12);
        $this->postJson('/api/applications/'.$applications[1]->public_id.'/select')->assertConflict();
    }

    public function test_recruiter_can_rotate_the_one_time_ingestion_key(): void
    {
        $offer = $this->offer();
        Sanctum::actingAs($offer->recruiter);

        $response = $this->postJson('/api/offers/'.$offer->public_id.'/ingestion-key')
            ->assertOk()
            ->assertJsonStructure(['ingestion_key', 'intake_url']);

        $this->assertNotSame('test-ingestion-key', $response->json('ingestion_key'));
        $this->assertSame(hash('sha256', $response->json('ingestion_key')), $offer->fresh()->ingestion_key_hash);
    }

    public function test_opening_a_cv_is_private_and_historized_as_read(): void
    {
        Storage::fake('local');
        $application = $this->application();
        Storage::disk('local')->put($application->cv_path, 'CV private content');

        $this->get('/api/applications/'.$application->public_id.'/cv')->assertUnauthorized();

        Sanctum::actingAs($application->offer->recruiter);
        $this->get('/api/applications/'.$application->public_id.'/cv')->assertOk();

        $this->assertNotNull($application->fresh()->read_at);
        $this->assertDatabaseHas('application_events', [
            'application_id' => $application->id,
            'type' => 'read_by_recruiter',
        ]);
    }

    public function test_company_logout_revokes_only_the_current_access_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('company-session')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('organizations', ['id' => $user->organization_id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    private function offer(?User $user = null): JobOffer
    {
        $user ??= User::factory()->create();

        return $user->jobOffers()->create([
            ...$this->offerPayload(),
            'ingestion_key_hash' => hash('sha256', 'test-ingestion-key'),
            'status' => 'open',
            'opens_at' => now(),
            'closes_at' => now()->addDay(),
        ]);
    }

    private function application(?JobOffer $offer = null, array $attributes = []): Application
    {
        $offer ??= $this->offer();

        return $offer->applications()->create(array_merge([
            'candidate_name' => 'Candidate Test',
            'candidate_email' => 'candidate@example.test',
            'source' => 'test-suite',
            'external_reference' => (string) fake()->uuid(),
            'cv_path' => 'cvs/test/cv.pdf',
            'cv_original_name' => 'cv.pdf',
            'status' => 'received',
            'status_token_hash' => hash('sha256', 'test-token'),
        ], $attributes));
    }

    private function offerPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Développeur Laravel et Vue',
            'company' => 'Acme',
            'location' => 'Paris',
            'description' => 'Nous recherchons une personne expérimentée pour construire un SaaS avec Laravel, Vue, PostgreSQL, Redis et des tests automatisés.',
            'criteria' => ['Laravel', 'Vue', 'TypeScript', 'PostgreSQL'],
            'rejection_message' => 'Merci pour votre candidature. Votre profil ne correspond pas au périmètre défini pour cette offre.',
            'final_rejection_message' => 'Merci pour votre candidature. Nous avons finalement retenu un autre profil pour cette campagne.',
            'screening_provider' => 'openai',
            'screening_model' => null,
            'scoring_provider' => 'anthropic',
            'scoring_model' => null,
        ], $overrides);
    }

    private function uploadedCv(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('cv.pdf', app(DemoCvPdf::class)->render($this->candidateData(), 0));
    }

    /** @return array{name: string, role: string, years: string, skills: string, summary: string} */
    private function candidateData(): array
    {
        return [
            'name' => 'Bob Candidat',
            'role' => 'Développeur full-stack',
            'years' => '2019 2022 2026',
            'skills' => 'Laravel PHP Vue TypeScript PostgreSQL Redis Docker tests automatisés',
            'summary' => 'Conception de produits SaaS et collaboration avec des équipes pluridisciplinaires.',
        ];
    }
}
