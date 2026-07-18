<?php

namespace Tests\Feature;

use App\Contracts\CandidateAnalyzer;
use App\Jobs\ScoreApplication;
use App\Jobs\ScreenApplication;
use App\Jobs\SendCandidateDecision;
use App\Models\Application;
use App\Services\ApplicationRetention;
use App\Services\CvTextExtractor;
use App\Services\DemoSandbox;
use App\Services\FinalizeOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicDemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('no-excuse.public_demo.enabled', true);
        config()->set('no-excuse.public_demo.processing_delay_seconds', 0);
        Cache::flush();
        Storage::fake('local');
        Queue::fake();
    }

    public function test_each_demo_visitor_receives_an_isolated_sandbox_with_twenty_fictional_cvs(): void
    {
        $first = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10', 'HTTP_USER_AGENT' => 'demo-visitor-one'])
            ->postJson('/api/demo/sessions')->assertCreated()->json();
        $second = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11', 'HTTP_USER_AGENT' => 'demo-visitor-two'])
            ->postJson('/api/demo/sessions')->assertCreated()->json();

        $this->assertNotSame($first['user']['organization']['uuid'], $second['user']['organization']['uuid']);
        $this->withToken($first['token'])->getJson('/api/offers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.applications_count', 20);
        $this->withToken($first['token'])->getJson('/api/offers/'.$second['demo']['offer_uuid'])->assertNotFound();
        $this->withToken($first['token'])->postJson('/api/offers', [])->assertForbidden();
        $this->withToken($first['token'])->putJson('/api/organization', [])->assertForbidden();
        $this->postJson('/api/setup', [])->assertForbidden();
        $this->assertDatabaseCount('applications', 40);
        Queue::assertPushed(ScreenApplication::class, 40);

        $this->getJson('/api/demo')
            ->assertOk()
            ->assertJson([
                'enabled' => true,
                'candidate_count' => 20,
                'active_sessions' => 2,
                'max_sessions' => 5,
                'at_capacity' => false,
            ]);

        $applications = Application::query()->get();
        $this->assertTrue($applications->every(fn ($application): bool => str_ends_with($application->cv_path, '.pdf')));
        $this->assertTrue($applications->every(fn ($application): bool => str_ends_with($application->cv_original_name, '.pdf')));
        $firstPdf = Storage::disk('local')->get($applications->firstOrFail()->cv_path);
        $this->assertStringStartsWith('%PDF-', $firstPdf);
        $this->assertGreaterThan(3000, strlen($firstPdf));
    }

    public function test_one_visitor_cannot_create_or_reset_a_second_sandbox(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20', 'HTTP_USER_AGENT' => 'single-demo-visitor']);
        $first = $this->postJson('/api/demo/sessions')->assertCreated()->json();

        $this->postJson('/api/demo/sessions')
            ->assertTooManyRequests()
            ->assertJsonFragment(['message' => 'Une seule sandbox est disponible par visiteur pendant la durée de la démonstration.']);
        $this->withToken($first['token'])->postJson('/api/demo/reset')
            ->assertTooManyRequests()
            ->assertJsonFragment(['message' => 'Cette sandbox a déjà été créée. Une seule sandbox est autorisée par visiteur.']);
        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseCount('applications', 20);
    }

    public function test_public_demo_capacity_is_hard_capped_at_five(): void
    {
        config()->set('no-excuse.public_demo.max_sessions', 100);

        $this->getJson('/api/demo')
            ->assertOk()
            ->assertJson(['max_sessions' => 5]);
    }

    public function test_demo_runs_the_real_pipeline_without_sending_email_or_accepting_external_cvs(): void
    {
        ['user' => $user, 'offer' => $offer] = app(DemoSandbox::class)->create();
        $analyzer = app(CandidateAnalyzer::class);
        $extractor = app(CvTextExtractor::class);

        $offer->applications()->pluck('id')->each(
            fn (int $id) => (new ScreenApplication($id))->handle($analyzer, $extractor),
        );
        $this->assertGreaterThanOrEqual(10, $offer->applications()->where('status', 'qualified')->count());
        $this->assertGreaterThan(0, $offer->applications()->where('status', 'rejected_out_of_scope')->count());
        $rejected = $offer->applications()->where('status', 'rejected_out_of_scope')->firstOrFail();
        $demoToken = $user->createToken('mail-preview')->plainTextToken;
        $this->withToken($demoToken)
            ->getJson('/api/offers/'.$offer->public_id.'/applications')
            ->assertOk()
            ->assertJsonFragment(['uuid' => $rejected->public_id, 'notification_status' => 'pending']);

        (new SendCandidateDecision($rejected->id))->handle(app(ApplicationRetention::class));
        $this->withToken($demoToken)
            ->getJson('/api/offers/'.$offer->public_id.'/applications')
            ->assertOk()
            ->assertJsonFragment(['uuid' => $rejected->public_id, 'notification_status' => 'previewed']);
        $this->withToken($demoToken)
            ->get('/api/applications/'.$rejected->public_id.'/decision-preview')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Bonjour '.$rejected->candidate_name, false)
            ->assertSee($offer->rejection_message, false)
            ->assertSee($rejected->scope_reason, false);

        $offer->applications()->where('status', 'qualified')->pluck('id')->each(
            fn (int $id) => (new ScoreApplication($id))->handle($analyzer, $extractor),
        );
        app(FinalizeOffer::class)->handle($offer);
        $this->assertSame(10, $offer->applications()->where('status', 'shortlisted')->count());

        Mail::fake();
        $application = $offer->applications()->where('status', 'shortlisted')->firstOrFail();
        (new SendCandidateDecision($application->id))->handle(app(ApplicationRetention::class));
        Mail::assertNothingSent();
        $this->assertNotNull($application->fresh()->notified_at);

        $this->withToken('invalid-demo-ingestion-key')
            ->postJson('/api/v1/intake/'.$offer->public_id.'/applications')
            ->assertForbidden();
        $this->assertTrue($user->organization->is_demo);
    }

    public function test_demo_cannot_be_reset_and_expired_data_is_completely_pruned(): void
    {
        ['user' => $user, 'offer' => $offer] = app(DemoSandbox::class)->create();
        $oldOfferUuid = $offer->public_id;
        $token = $user->createToken('test-demo')->plainTextToken;

        $this->withToken($token)->postJson('/api/demo/reset')->assertTooManyRequests();
        $this->assertDatabaseCount('applications', 20);

        $organization = $user->organization;
        $organization->update(['expires_at' => now()->subMinute()]);
        $this->artisan('demo:prune')->assertSuccessful();

        $this->assertDatabaseMissing('organizations', ['id' => $organization->id]);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertDatabaseCount('applications', 0);
        Storage::disk('local')->assertMissing('cvs/'.$oldOfferUuid);
        auth()->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }
}
