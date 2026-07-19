<?php

namespace Tests\Feature;

use App\Jobs\SendCandidateDecision;
use App\Mail\CandidateDecisionMail;
use App\Mail\LoginVerificationCodeMail;
use App\Mail\PasswordResetMail;
use App\Mail\TeamInvitationMail;
use App\Models\Application;
use App\Models\JobOffer;
use App\Models\User;
use App\Services\ApplicationRetention;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_throttled_and_tokens_expire(): void
    {
        Cache::flush();
        $user = User::factory()->create(['email' => 'owner@example.test']);
        $login = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->assertJsonStructure(['token', 'expires_at']);
        $this->assertTrue(now()->addHours(11)->lt($login->json('expires_at')));

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/auth/login', ['email' => 'unknown@example.test', 'password' => 'wrong']);
        }
        $this->postJson('/api/auth/login', ['email' => 'unknown@example.test', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_team_member_activates_a_one_time_invitation_without_shared_password(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $this->postJson('/api/organization/members', [
            'name' => 'Recruteuse invitée', 'email' => 'invitee@example.test', 'role' => 'recruiter',
        ])->assertCreated();
        $token = '';
        Mail::assertSent(TeamInvitationMail::class, function (TeamInvitationMail $mail) use (&$token): bool {
            $token = $mail->token;

            return true;
        });
        auth()->forgetGuards();
        $this->postJson('/api/auth/activate', [
            'email' => 'invitee@example.test', 'token' => $token,
            'password' => 'Secure-password-2026', 'password_confirmation' => 'Secure-password-2026',
        ])->assertOk()->assertJsonStructure(['token']);
        $this->postJson('/api/auth/activate', [
            'email' => 'invitee@example.test', 'token' => $token,
            'password' => 'Secure-password-2026', 'password_confirmation' => 'Secure-password-2026',
        ])->assertUnprocessable();
    }

    public function test_pending_team_invitation_can_be_rotated_and_resent(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $memberUuid = $this->postJson('/api/organization/members', [
            'name' => 'Recruteuse invitée', 'email' => 'pending@example.test', 'role' => 'viewer',
        ])->assertCreated()->json('data.uuid');
        $firstHash = User::query()->where('public_id', $memberUuid)->value('invitation_token_hash');

        $this->postJson("/api/organization/members/$memberUuid/resend-invitation")->assertOk();

        $this->assertNotSame($firstHash, User::query()->where('public_id', $memberUuid)->value('invitation_token_hash'));
        Mail::assertSent(TeamInvitationMail::class, 2);

        $member = User::query()->where('public_id', $memberUuid)->firstOrFail();
        $member->createToken('pending-access');
        $this->deleteJson("/api/organization/members/$memberUuid")->assertNoContent();
        $this->assertDatabaseMissing('users', ['public_id' => $memberUuid]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $member->id]);
    }

    public function test_password_reset_revokes_existing_tokens(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'reset@example.test']);
        $user->createToken('existing');
        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertAccepted();
        $token = '';
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$token): bool {
            $token = $mail->token;

            return true;
        });

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email, 'token' => $token,
            'password' => 'New-secure-password-2026', 'password_confirmation' => 'New-secure-password-2026',
        ])->assertOk()->assertJsonStructure(['token']);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_email_mfa_is_required_when_enabled(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'mfa@example.test', 'mfa_email_enabled' => true]);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertAccepted()->assertJson(['mfa_required' => true]);
        $code = '';
        Mail::assertSent(LoginVerificationCodeMail::class, function (LoginVerificationCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password', 'mfa_code' => $code])
            ->assertOk()->assertJsonStructure(['token']);
    }

    public function test_malformed_pdf_is_rejected_before_storage(): void
    {
        Storage::fake('local');
        $offer = $this->offer();
        $this->withToken('ingestion-key')->post('/api/v1/intake/'.$offer->public_id.'/applications', [
            'source' => 'ats', 'external_reference' => 'bad-pdf', 'candidate_name' => 'Candidate',
            'candidate_email' => 'candidate@example.test',
            'cv' => UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.7\nmissing end marker"),
        ])->assertUnprocessable()->assertInvalid('cv');
        $this->assertSame([], Storage::disk('local')->allFiles('cvs'));
    }

    public function test_candidate_mail_job_is_idempotent(): void
    {
        Mail::fake();
        Storage::fake('local');
        $application = $this->application(['status' => 'rejected_final', 'final_score' => 70, 'notification_state' => 'pending']);
        $job = new SendCandidateDecision($application->id);
        $job->handle(app(ApplicationRetention::class));
        $job->handle(app(ApplicationRetention::class));

        Mail::assertSent(CandidateDecisionMail::class, 1);
    }

    public function test_candidate_personal_data_can_be_anonymized_after_final_decision(): void
    {
        Storage::fake('local');
        $application = $this->application([
            'status' => 'rejected_final',
            'cover_letter' => 'Privé',
            'pseudonymized_cv_text' => '[CANDIDATE_NAME] maîtrise PHP.',
            'pseudonymization_version' => 'test-v1',
            'pseudonymized_at' => now(),
        ]);
        Storage::disk('local')->put($application->cv_path, '%PDF-test');
        $annotation = $application->annotations()->make(['body' => 'Note privée']);
        $annotation->user()->associate($application->offer->recruiter);
        $annotation->save();
        app(ApplicationRetention::class)->anonymize($application);
        $application->refresh();

        $this->assertSame('Candidat supprimé', $application->candidate_name);
        $this->assertNull($application->cover_letter);
        $this->assertNull($application->cv_path);
        $this->assertNull($application->pseudonymized_cv_text);
        $this->assertNotNull($application->personal_data_deleted_at);
        $this->assertDatabaseMissing('application_annotations', ['application_id' => $application->id]);
    }

    public function test_protected_characteristics_cannot_be_used_as_scoring_criteria(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $this->postJson('/api/offers', [
            'title' => 'Développeur', 'company' => 'Acme', 'description' => str_repeat('Description ', 10),
            'criteria' => ['PHP', 'moins de 35 ans'], 'rejection_message' => str_repeat('Message ', 4),
            'final_rejection_message' => str_repeat('Message ', 4),
        ])->assertUnprocessable()->assertInvalid('criteria.1');
    }

    public function test_operational_status_is_private_and_restricted_to_managers(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        Sanctum::actingAs($owner);
        $this->getJson('/api/operations/status')->assertOk()->assertJsonStructure([
            'queues' => ['screening', 'scoring', 'notifications'], 'processing_failures',
            'notification_failures', 'closing_campaigns', 'failed_jobs', 'checked_at',
        ]);

        $viewer = User::factory()->create(['organization_id' => $owner->organization_id, 'role' => 'viewer']);
        Sanctum::actingAs($viewer);
        $this->getJson('/api/operations/status')->assertForbidden();
    }

    private function offer(): JobOffer
    {
        $user = User::factory()->create();

        return $user->jobOffers()->create([
            'organization_id' => $user->organization_id,
            'title' => 'Développeur', 'company' => 'Acme', 'description' => str_repeat('Description ', 10),
            'criteria' => ['PHP'], 'rejection_message' => str_repeat('Message ', 4),
            'final_rejection_message' => str_repeat('Message ', 4), 'ingestion_key_hash' => hash('sha256', 'ingestion-key'),
            'status' => 'open', 'opens_at' => now(), 'closes_at' => now()->addDay(),
        ]);
    }

    private function application(array $attributes = []): Application
    {
        $offer = $this->offer();

        return $offer->applications()->create(array_merge([
            'candidate_name' => 'Candidate', 'candidate_email' => 'candidate@example.test',
            'source' => 'test', 'external_reference' => fake()->uuid(), 'cv_path' => 'cvs/test/cv.pdf',
            'cv_original_name' => 'cv.pdf', 'status' => 'received', 'status_token_hash' => hash('sha256', 'token'),
        ], $attributes));
    }
}
