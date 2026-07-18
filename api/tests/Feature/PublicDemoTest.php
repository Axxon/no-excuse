<?php

namespace Tests\Feature;

use App\Contracts\CandidateAnalyzer;
use App\Jobs\ScoreApplication;
use App\Jobs\ScreenApplication;
use App\Jobs\SendCandidateDecision;
use App\Services\CvTextExtractor;
use App\Services\DemoSandbox;
use App\Services\FinalizeOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        Storage::fake('local');
        Queue::fake();
    }

    public function test_each_demo_visitor_receives_an_isolated_sandbox_with_twenty_fictional_cvs(): void
    {
        $first = $this->postJson('/api/demo/sessions')->assertCreated()->json();
        $second = $this->postJson('/api/demo/sessions')->assertCreated()->json();

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

        $offer->applications()->where('status', 'qualified')->pluck('id')->each(
            fn (int $id) => (new ScoreApplication($id))->handle($analyzer, $extractor),
        );
        app(FinalizeOffer::class)->handle($offer);
        $this->assertSame(10, $offer->applications()->where('status', 'shortlisted')->count());

        Mail::fake();
        $application = $offer->applications()->where('status', 'shortlisted')->firstOrFail();
        (new SendCandidateDecision($application->id))->handle();
        Mail::assertNothingSent();
        $this->assertNotNull($application->fresh()->notified_at);

        $this->withToken('invalid-demo-ingestion-key')
            ->postJson('/api/v1/intake/'.$offer->public_id.'/applications')
            ->assertForbidden();
        $this->assertTrue($user->organization->is_demo);
    }

    public function test_demo_can_be_reset_and_expired_data_is_completely_pruned(): void
    {
        ['user' => $user, 'offer' => $offer] = app(DemoSandbox::class)->create();
        $oldOfferUuid = $offer->public_id;
        $token = $user->createToken('test-demo')->plainTextToken;

        $reset = $this->withToken($token)->postJson('/api/demo/reset')->assertOk()->json();
        $this->assertNotSame($oldOfferUuid, $reset['offer_uuid']);
        $this->assertDatabaseCount('applications', 20);

        $organization = $user->organization;
        $organization->update(['expires_at' => now()->subMinute()]);
        $this->artisan('demo:prune')->assertSuccessful();

        $this->assertDatabaseMissing('organizations', ['id' => $organization->id]);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertDatabaseCount('applications', 0);
        Storage::disk('local')->assertMissing('cvs/'.$reset['offer_uuid']);
        auth()->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }
}
