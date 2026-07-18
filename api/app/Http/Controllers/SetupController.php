<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class SetupController extends Controller
{
    public function status(): JsonResponse
    {
        return response()->json(['configured' => config('no-excuse.public_demo.enabled') || User::query()->exists()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if(config('no-excuse.public_demo.enabled'), 403, 'Cette instance est réservée aux démonstrations éphémères.');
        abort_if(User::query()->exists(), 409, 'Cette instance est déjà configurée.');
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:160'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $user = DB::transaction(function () use ($data): User {
            $organization = Organization::create([
                'name' => $data['company_name'],
                'notification_sender_name' => 'Équipe recrutement '.$data['company_name'],
                'notification_reply_to' => $data['email'],
                'screening_prompt' => config('no-excuse.prompts.screening'),
                'scoring_prompt' => config('no-excuse.prompts.scoring'),
            ]);

            return $organization->users()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'owner',
            ]);
        });

        return response()->json(AuthController::payloadFor($user), 201);
    }
}
