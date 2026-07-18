<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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

    public function storeMember(Request $request): JsonResponse
    {
        abort_unless($request->user()->canManageTeam(), 403);
        abort_if($request->user()->organization->is_demo, 403, 'La démonstration ne crée pas de comptes permanents.');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['admin', 'recruiter', 'viewer'])],
            'password' => ['required', Password::min(10)->letters()->numbers()],
        ]);
        $member = $request->user()->organization->users()->create($data);

        return response()->json(['data' => $this->memberPayload($member)], 201);
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
        ];
    }

    private function memberPayload(User $user): array
    {
        return ['uuid' => $user->public_id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role];
    }
}
