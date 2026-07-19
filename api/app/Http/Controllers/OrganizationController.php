<?php

namespace App\Http\Controllers;

use App\Mail\MailConfigurationTest;
use App\Mail\TeamInvitationMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class OrganizationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->organizationPayload($request)]);
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()->canManageTeam(), 403);
        abort_if($request->user()->organization->is_demo, 403, 'Les réglages de la sandbox sont verrouillés.');
        $providers = array_keys(config('no-excuse.ai.providers'));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'notification_sender_name' => ['required', 'string', 'max:160'],
            'notification_reply_to' => ['required', 'email', 'max:255'],
            'default_screening_provider' => ['required', Rule::in($providers)],
            'default_screening_model' => ['nullable', 'string', 'max:160'],
            'default_scoring_provider' => ['required', Rule::in($providers)],
            'default_scoring_model' => ['nullable', 'string', 'max:160'],
            'screening_workers' => ['required', 'integer', 'min:1', 'max:10'],
            'scoring_workers' => ['required', 'integer', 'min:1', 'max:10'],
            'screening_prompt' => ['required', 'string', 'min:40', 'max:8000'],
            'scoring_prompt' => ['required', 'string', 'min:40', 'max:8000'],
            'rejected_cv_retention_days' => ['sometimes', 'integer', 'min:0', 'max:730'],
            'selected_cv_retention_days' => ['sometimes', 'integer', 'min:1', 'max:730'],
            'candidate_data_retention_days' => ['sometimes', 'integer', 'min:30', 'max:1825'],
        ]);
        $request->user()->organization->update($data);

        return $this->show($request);
    }

    public function members(Request $request): JsonResponse
    {
        $members = $request->user()->organization->users()->orderBy('name')->get()
            ->map(fn (User $user): array => $this->memberPayload($user));

        return response()->json(['data' => $members]);
    }

    public function sendTestMail(Request $request): JsonResponse
    {
        abort_unless($request->user()->canManageTeam(), 403, 'Cette action est réservée aux responsables.');
        abort_if($request->user()->organization->is_demo, 403, 'La démonstration n’envoie aucun e-mail.');

        $organization = $request->user()->organization;

        try {
            Mail::to($request->user()->email)->send(new MailConfigurationTest(
                $organization->notification_sender_name,
                $organization->notification_reply_to,
            ));
        } catch (Throwable $exception) {
            logger()->warning('Mail configuration test failed.', ['exception_class' => $exception::class]);

            return response()->json(['message' => 'L’e-mail de test n’a pas pu être envoyé. Vérifiez la configuration du transport.'], 503);
        }

        return response()->json(['message' => 'E-mail de test envoyé à votre adresse.']);
    }

    public function storeMember(Request $request): JsonResponse
    {
        abort_unless($request->user()->canManageTeam(), 403);
        abort_if($request->user()->organization->is_demo, 403, 'La démonstration ne crée pas de comptes permanents.');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['admin', 'recruiter', 'viewer'])],
        ]);
        $token = Str::random(64);
        $member = $request->user()->organization->users()->create([
            ...$data,
            'password' => Str::password(40),
            'invitation_token_hash' => hash('sha256', $token),
            'invitation_expires_at' => now()->addDay(),
        ]);
        try {
            Mail::to($member->email)->send(new TeamInvitationMail($member->load('organization'), $token));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Le compte a été créé, mais l’invitation n’a pas pu être envoyée. Utilisez « Renvoyer » depuis la liste.'], 503);
        }

        return response()->json(['data' => $this->memberPayload($member)], 201);
    }

    public function resendMemberInvitation(Request $request, User $member): JsonResponse
    {
        abort_unless($request->user()->canManageTeam(), 403);
        abort_if($request->user()->organization->is_demo, 403);
        abort_unless($member->organization_id === $request->user()->organization_id, 404);
        abort_unless($member->invitation_token_hash, 409, 'Ce compte est déjà activé.');

        $token = Str::random(64);
        $member->update(['invitation_token_hash' => hash('sha256', $token), 'invitation_expires_at' => now()->addDay()]);
        Mail::to($member->email)->send(new TeamInvitationMail($member->load('organization'), $token));

        return response()->json(['message' => 'Invitation renvoyée.']);
    }

    public function destroyMember(Request $request, User $member): JsonResponse
    {
        abort_unless($request->user()->canManageTeam(), 403);
        abort_if($request->user()->organization->is_demo, 403);
        abort_unless($member->organization_id === $request->user()->organization_id, 404);
        abort_if($member->is($request->user()), 409, 'Vous ne pouvez pas supprimer votre propre accès.');
        abort_if($member->role === 'owner', 409, 'Le compte responsable ne peut pas être supprimé depuis cette interface.');

        DB::transaction(function () use ($member): void {
            $member->tokens()->delete();
            $member->delete();
        });

        return response()->json([], 204);
    }

    private function organizationPayload(Request $request): array
    {
        $organization = $request->user()->organization;

        return [
            'uuid' => $organization->public_id,
            'name' => $organization->name,
            'notification_sender_name' => $organization->notification_sender_name,
            'notification_reply_to' => $organization->notification_reply_to,
            'default_screening_provider' => $organization->default_screening_provider,
            'default_screening_model' => $organization->default_screening_model,
            'default_scoring_provider' => $organization->default_scoring_provider,
            'default_scoring_model' => $organization->default_scoring_model,
            'screening_workers' => $organization->screening_workers,
            'scoring_workers' => $organization->scoring_workers,
            'screening_prompt' => $organization->screening_prompt ?: config('no-excuse.prompts.screening'),
            'scoring_prompt' => $organization->scoring_prompt ?: config('no-excuse.prompts.scoring'),
            'rejected_cv_retention_days' => $organization->rejected_cv_retention_days,
            'selected_cv_retention_days' => $organization->selected_cv_retention_days,
            'candidate_data_retention_days' => $organization->candidate_data_retention_days,
        ];
    }

    private function memberPayload(User $user): array
    {
        return ['uuid' => $user->public_id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role, 'invitation_pending' => filled($user->invitation_token_hash)];
    }
}
