<?php

namespace App\Http\Controllers;

use App\Mail\LoginVerificationCodeMail;
use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Services\DemoSandbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string'], 'mfa_code' => ['sometimes', 'digits:6']]);
        $user = User::query()->where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Identifiants invalides.'], 422);
        }
        if ($user->mfa_email_enabled) {
            if (! isset($data['mfa_code'])) {
                $code = (string) random_int(100000, 999999);
                $user->update(['mfa_code_hash' => Hash::make($code), 'mfa_code_expires_at' => now()->addMinutes(10)]);
                Mail::to($user->email)->send(new LoginVerificationCodeMail($code));

                return response()->json(['mfa_required' => true, 'message' => 'Un code de vérification vient d’être envoyé.'], 202);
            }
            abort_unless($user->mfa_code_expires_at?->isFuture() && Hash::check($data['mfa_code'], (string) $user->mfa_code_hash), 422, 'Code de vérification invalide ou expiré.');
            $user->update(['mfa_code_hash' => null, 'mfa_code_expires_at' => null]);
        }

        return response()->json(self::payloadFor($user));
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['user' => self::userPayload($user)]);
    }

    public function activate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()->mixedCase()],
        ]);
        $user = User::query()->where('email', Str::lower($data['email']))
            ->where('invitation_token_hash', hash('sha256', $data['token']))->first();
        abort_unless($user && $user->invitation_expires_at?->isFuture(), 422, 'Cette invitation est invalide ou expirée.');
        $user->update([
            'password' => $data['password'], 'email_verified_at' => now(),
            'invitation_token_hash' => null, 'invitation_expires_at' => null,
        ]);

        return response()->json(self::payloadFor($user));
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::query()->where('email', Str::lower($data['email']))->whereNull('invitation_token_hash')->first();
        if ($user) {
            $token = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($token), 'created_at' => now()],
            );
            Mail::to($user->email)->send(new PasswordResetMail($user, $token));
        }

        return response()->json(['message' => 'Si ce compte existe, un lien de réinitialisation vient d’être envoyé.'], 202);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'], 'token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()->mixedCase()],
        ]);
        $email = Str::lower($data['email']);
        $reset = DB::table('password_reset_tokens')->where('email', $email)->first();
        abort_unless($reset && now()->subHour()->lte($reset->created_at) && Hash::check($data['token'], $reset->token), 422, 'Ce lien est invalide ou expiré.');
        $user = User::query()->where('email', $email)->firstOrFail();
        DB::transaction(function () use ($user, $data, $email): void {
            $user->update(['password' => $data['password']]);
            $user->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $email)->delete();
        });

        return response()->json(self::payloadFor($user));
    }

    public function logout(Request $request, DemoSandbox $sandbox): JsonResponse
    {
        $user = $request->user();
        if ($user->organization?->is_demo) {
            $sandbox->destroy($user->organization);
        } else {
            $user->currentAccessToken()?->delete();
        }

        return response()->json([], 204);
    }

    public function configureMfa(Request $request): JsonResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'password' => ['required', 'string']]);
        abort_unless(Hash::check($data['password'], $request->user()->password), 422, 'Mot de passe incorrect.');
        $request->user()->update(['mfa_email_enabled' => $data['enabled'], 'mfa_code_hash' => null, 'mfa_code_expires_at' => null]);

        return response()->json(['mfa_email_enabled' => (bool) $data['enabled']]);
    }

    public static function payloadFor(User $user): array
    {
        $expiresAt = $user->organization?->is_demo
            ? $user->organization->expires_at
            : now()->addHours(max(1, (int) config('no-excuse.auth.token_lifetime_hours')));

        return [
            'token' => $user->createToken('web', ['*'], $expiresAt)->plainTextToken,
            'expires_at' => $expiresAt?->toIso8601String(),
            'user' => self::userPayload($user),
        ];
    }

    private static function userPayload(User $user): array
    {
        return [
            'uuid' => $user->public_id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'mfa_email_enabled' => $user->mfa_email_enabled,
            'organization' => $user->organization ? [
                'uuid' => $user->organization->public_id,
                'name' => $user->organization->name,
                'is_demo' => $user->organization->is_demo,
                'expires_at' => $user->organization->expires_at?->toIso8601String(),
            ] : null,
        ];
    }
}
