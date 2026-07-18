<?php

namespace Tests\Feature;

use App\Jobs\SendCandidateDecision;
use App\Mail\CandidateDecisionMail;
use App\Mail\DemoSlotAvailableMail;
use App\Mail\MailConfigurationTest;
use App\Models\User;
use App\Services\ApplicationRetention;
use App\Services\DemoSandbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RetentionMailAndWaitlistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
    }

    public function test_rejected_cv_is_deleted_but_audit_trace_is_retained(): void
    {
        $user = User::factory()->create();
        $offer = $user->jobOffers()->create([
            'title' => 'Développeur', 'company' => 'Acme', 'description' => 'Poste test', 'criteria' => ['PHP'],
            'rejection_message' => 'Non retenu', 'final_rejection_message' => 'Non retenu',
            'ingestion_key_hash' => hash('sha256', 'key'), 'status' => 'closed',
        ]);
        $path = 'cvs/test/rejected.pdf';
        Storage::disk('local')->put($path, '%PDF-test');
        $application = $offer->applications()->create([
            'candidate_name' => 'Test Candidate', 'candidate_email' => 'candidate@example.test',
            'cv_path' => $path, 'cv_original_name' => 'cv.pdf', 'status' => 'rejected_final',
            'scope_score' => 80, 'final_score' => 72, 'notified_at' => now(),
            'status_token_hash' => hash('sha256', 'token'),
        ]);

        $this->assertTrue(app(ApplicationRetention::class)->purgeRejectedCv($application));
        Storage::disk('local')->assertMissing($path);
        $application->refresh();
        $this->assertNull($application->cv_path);
        $this->assertSame('rejected_final', $application->status);
        $this->assertSame(72.0, $application->final_score);
        $this->assertDatabaseHas('application_events', ['application_id' => $application->id, 'type' => 'cv_deleted_by_retention']);
        $token = $user->createToken('no-preview-outside-demo')->plainTextToken;
        $this->withToken($token)->get('/api/applications/'.$application->public_id.'/decision-preview')->assertNotFound();
    }

    public function test_out_of_scope_notification_moves_from_pending_to_sent_and_contains_the_ai_reason(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $offer = $user->jobOffers()->create([
            'title' => 'Développeur Laravel', 'company' => 'Acme', 'description' => 'Poste test', 'criteria' => ['PHP'],
            'rejection_message' => 'Merci pour votre candidature.', 'final_rejection_message' => 'Non retenu',
            'ingestion_key_hash' => hash('sha256', 'key'), 'status' => 'open',
        ]);
        $application = $offer->applications()->create([
            'candidate_name' => 'Test Candidate', 'candidate_email' => 'candidate@example.test',
            'cv_path' => 'cvs/test/rejected.pdf', 'cv_original_name' => 'cv.pdf', 'status' => 'rejected_out_of_scope',
            'scope_reason' => 'Le CV ne démontre pas une expérience PHP attendue pour ce poste.',
            'candidate_feedback' => 'Ce message personnalisé ne doit pas apparaître.',
            'status_token_hash' => hash('sha256', 'token'),
        ]);
        $token = $user->createToken('notification-status')->plainTextToken;

        $this->withToken($token)->getJson('/api/offers/'.$offer->public_id.'/applications')
            ->assertOk()
            ->assertJsonFragment(['uuid' => $application->public_id, 'notification_status' => 'pending']);

        (new SendCandidateDecision($application->id))->handle(app(ApplicationRetention::class));

        Mail::assertSent(CandidateDecisionMail::class, function (CandidateDecisionMail $mail) use ($application): bool {
            $rendered = $mail->render();

            return str_contains($rendered, $application->scope_reason)
                && ! str_contains($rendered, $application->candidate_feedback);
        });
        $this->withToken($token)->getJson('/api/offers/'.$offer->public_id.'/applications')
            ->assertOk()
            ->assertJsonFragment(['uuid' => $application->public_id, 'notification_status' => 'sent']);
    }

    public function test_final_decision_email_contains_the_personalized_candidate_message(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $offer = $user->jobOffers()->create([
            'title' => 'Développeur Laravel', 'company' => 'Acme', 'description' => 'Poste test', 'criteria' => ['PHP'],
            'rejection_message' => 'Merci pour votre candidature.', 'final_rejection_message' => 'Non retenu',
            'ingestion_key_hash' => hash('sha256', 'key'), 'status' => 'closed',
        ]);
        $message = 'Votre échange a été très apprécié par toute l’équipe.';
        $application = $offer->applications()->create([
            'candidate_name' => 'Test Candidate', 'candidate_email' => 'candidate@example.test',
            'cv_path' => 'cvs/test/finalist.pdf', 'cv_original_name' => 'cv.pdf', 'status' => 'rejected_final',
            'final_score' => 82, 'candidate_feedback' => $message,
            'status_token_hash' => hash('sha256', 'token'),
        ]);

        (new SendCandidateDecision($application->id))->handle(app(ApplicationRetention::class));

        Mail::assertSent(CandidateDecisionMail::class, fn (CandidateDecisionMail $mail): bool => str_contains($mail->render(), $message));
    }

    public function test_waitlist_is_used_when_demo_capacity_is_full_and_notifies_after_expiry(): void
    {
        config()->set('no-excuse.public_demo.enabled', true);
        config()->set('no-excuse.public_demo.max_sessions', 1);
        config()->set('no-excuse.public_demo.processing_delay_seconds', 0);
        ['user' => $user] = app(DemoSandbox::class)->create();

        $this->getJson('/api/demo')
            ->assertOk()
            ->assertJson(['active_sessions' => 1, 'max_sessions' => 1, 'at_capacity' => true]);

        $this->postJson('/api/demo/sessions')->assertServiceUnavailable();
        $this->postJson('/api/demo/waitlist', ['email' => 'sebastien.grans@gmail.com', 'locale' => 'fr'])->assertAccepted();
        $this->assertDatabaseHas('demo_waitlist_entries', ['email_hash' => hash('sha256', 'sebastien.grans@gmail.com'), 'status' => 'waiting']);
        $this->getJson('/api/demo')
            ->assertOk()
            ->assertJsonPath('waitlist_count', 1)
            ->assertJsonPath('waitlist.0.position', 1)
            ->assertJsonPath('waitlist.0.masked_email', 's*******n.g***s@g***l.com')
            ->assertJsonMissing(['sebastien.grans@gmail.com']);

        $user->organization->update(['expires_at' => now()->subMinute()]);
        $this->artisan('demo:prune')->assertSuccessful();
        Mail::fake();
        $this->artisan('demo:notify-waitlist')->assertSuccessful();
        Mail::assertSent(DemoSlotAvailableMail::class, function (DemoSlotAvailableMail $mail): bool {
            return $mail->envelope()->subject === '[no-excuse] C’est votre tour — une place est disponible'
                && str_contains($mail->render(), 'C’est votre tour')
                && str_contains($mail->render(), 'Lancer ma démo');
        });
        $this->assertDatabaseHas('demo_waitlist_entries', ['email_hash' => hash('sha256', 'sebastien.grans@gmail.com'), 'status' => 'notified']);
        $this->getJson('/api/demo')->assertJsonPath('waitlist_count', 0)->assertJsonPath('waitlist', []);
    }

    public function test_mail_configuration_command_sends_a_safe_test_message(): void
    {
        Mail::fake();
        $this->artisan('mail:test', ['email' => 'admin@example.test'])->assertSuccessful();
        Mail::assertSent(MailConfigurationTest::class, 1);
    }
}
