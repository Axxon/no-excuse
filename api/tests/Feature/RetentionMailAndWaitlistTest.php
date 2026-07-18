<?php

namespace Tests\Feature;

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
    }

    public function test_waitlist_is_used_when_demo_capacity_is_full_and_notifies_after_expiry(): void
    {
        config()->set('no-excuse.public_demo.enabled', true);
        config()->set('no-excuse.public_demo.max_sessions', 1);
        config()->set('no-excuse.public_demo.processing_delay_seconds', 0);
        ['user' => $user] = app(DemoSandbox::class)->create();

        $this->postJson('/api/demo/sessions')->assertServiceUnavailable();
        $this->postJson('/api/demo/waitlist', ['email' => 'rh@example.test', 'locale' => 'fr'])->assertAccepted();
        $this->assertDatabaseHas('demo_waitlist_entries', ['email_hash' => hash('sha256', 'rh@example.test'), 'status' => 'waiting']);

        $user->organization->update(['expires_at' => now()->subMinute()]);
        $this->artisan('demo:prune')->assertSuccessful();
        Mail::fake();
        $this->artisan('demo:notify-waitlist')->assertSuccessful();
        Mail::assertSent(DemoSlotAvailableMail::class, 1);
        $this->assertDatabaseHas('demo_waitlist_entries', ['email_hash' => hash('sha256', 'rh@example.test'), 'status' => 'notified']);
    }

    public function test_mail_configuration_command_sends_a_safe_test_message(): void
    {
        Mail::fake();
        $this->artisan('mail:test', ['email' => 'admin@example.test'])->assertSuccessful();
        Mail::assertSent(MailConfigurationTest::class, 1);
    }
}
