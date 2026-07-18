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

    public function test_recruiter_can_register_and_configure_two_ai_providers(): void
    {
        $auth = $this->postJson('/api/auth/register', [
            'name' => 'Alice RH',
            'email' => 'alice@example.test',
            'password' => 'strong-password-2026',
            'password_confirmation' => 'strong-password-2026',
        ])->assertCreated()->json();

        $this->withToken($auth['token'])->postJson('/api/offers', $this->offerPayload([
            'screening_provider' => 'openai',
            'screening_model' => 'small-cost-model',
            'scoring_provider' => 'anthropic',
            'scoring_model' => 'fine-model',
        ]))->assertCreated()
            ->assertJsonPath('data.screening_provider', 'openai')
            ->assertJsonPath('data.scoring_provider', 'anthropic');
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
            'cv' => UploadedFile::fake()->createWithContent('cv.txt', 'Laravel Vue TypeScript PostgreSQL Redis tests automatisés 2022 2025'),
        ];
        $this->withToken('wrong-key')->post('/api/v1/intake/'.$offer->public_id.'/applications', $payload)->assertNotFound();

        $payload['cv'] = UploadedFile::fake()->createWithContent('cv.txt', 'Laravel Vue TypeScript PostgreSQL Redis tests automatisés 2022 2025');
        $response = $this->withToken('test-ingestion-key')
            ->post('/api/v1/intake/'.$offer->public_id.'/applications', $payload)
            ->assertAccepted()
            ->assertJsonStructure(['application_reference', 'status', 'duplicate']);

        $application = Application::where('public_id', $response->json('application_reference'))->firstOrFail();
        $this->assertSame('linkedin', $application->source);
        Storage::disk('local')->assertExists($application->cv_path);
        Queue::assertPushed(ScreenApplication::class, fn ($job) => $job->applicationId === $application->id);

        $payload['cv'] = UploadedFile::fake()->createWithContent('cv.txt', 'same candidate retry');
        $this->withToken('test-ingestion-key')
            ->post('/api/v1/intake/'.$offer->public_id.'/applications', $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('application_reference', $application->public_id);
    }

    public function test_two_stage_pipeline_scores_a_qualified_application(): void
    {
        Queue::fake();
        Storage::fake('local');
        $application = $this->application();
        Storage::disk('local')->put($application->cv_path, 'Laravel Vue TypeScript PostgreSQL Redis');
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
        $this->assertSame('rejected_final', $applications->last()->fresh()->status);
        Queue::assertPushed(SendCandidateDecision::class, 12);
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
            'cv_path' => 'cvs/test/cv.txt',
            'cv_original_name' => 'cv.txt',
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
}
