<?php

namespace App\Http\Controllers;

use App\Http\Concerns\IssuesAuthSession;
use App\Models\WebauthnChallenge;
use App\Models\WebauthnCredential;
use App\Services\Auth\TwoFactorChallengeService;
use App\Services\Auth\Webauthn\WebauthnChallengeService;
use App\Services\Auth\Webauthn\WebauthnException;
use App\Services\Auth\Webauthn\WebauthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebauthnController extends Controller
{
    use IssuesAuthSession;

    public function __construct(
        private WebauthnService $webauthn,
        private WebauthnChallengeService $challenges,
        private TwoFactorChallengeService $twoFactorChallenges,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $credentials = $request->user()->webauthnCredentials()
            ->latest()
            ->get(['id', 'name', 'aaguid', 'last_used_at', 'created_at'])
            ->map(fn (WebauthnCredential $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'aaguid' => $c->aaguid,
                'last_used_at' => $c->last_used_at,
                'created_at' => $c->created_at,
            ]);

        return response()->json(['credentials' => $credentials]);
    }

    public function registerOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        $max = (int) config('webauthn.max_credentials_per_user');

        if ($user->webauthnCredentials()->count() >= $max) {
            throw WebauthnException::limitReached($max);
        }

        $options = $this->webauthn->serialize($this->webauthn->creationOptions($user));
        $token = $this->challenges->issue($user, WebauthnChallenge::TYPE_REGISTRATION, $options);

        return response()->json([
            'token' => $token,
            'options' => json_decode($options, true),
        ]);
    }

    public function registerVerify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:100'],
            'response' => ['required', 'array'],
        ]);

        $user = $request->user();
        $challenge = $this->challenges->consume($data['token'], WebauthnChallenge::TYPE_REGISTRATION);

        if ($challenge === null || $challenge->user_id !== $user->id) {
            throw WebauthnException::invalidChallenge();
        }

        $credential = $this->webauthn->verifyRegistration(
            $user,
            $challenge->options,
            json_encode($data['response']),
            $data['name'] ?? null,
        );

        return response()->json([
            'message' => 'Passkey registered.',
            'credential' => [
                'id' => $credential->id,
                'name' => $credential->name,
                'aaguid' => $credential->aaguid,
                'last_used_at' => $credential->last_used_at,
                'created_at' => $credential->created_at,
            ],
        ], 201);
    }

    public function update(Request $request, WebauthnCredential $credential): JsonResponse
    {
        abort_unless($credential->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $credential->update(['name' => $data['name']]);

        return response()->json(['message' => 'Passkey renamed.']);
    }

    public function destroy(Request $request, WebauthnCredential $credential): JsonResponse
    {
        abort_unless($credential->user_id === $request->user()->id, 404);

        $credential->delete();

        return response()->json(['message' => 'Passkey removed.']);
    }

    public function loginOptions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'two_factor_token' => ['nullable', 'string'],
        ]);

        $user = isset($data['two_factor_token'])
            ? $this->twoFactorChallenges->resolve($data['two_factor_token'])
            : null;

        if (isset($data['two_factor_token']) && $user === null) {
            return response()->json(['message' => 'Invalid or expired token.'], 401);
        }

        $options = $this->webauthn->serialize($this->webauthn->requestOptions($user));
        $token = $this->challenges->issue($user, WebauthnChallenge::TYPE_AUTHENTICATION, $options);

        return response()->json([
            'token' => $token,
            'options' => json_decode($options, true),
        ]);
    }

    public function loginVerify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'response' => ['required', 'array'],
            'two_factor_token' => ['nullable', 'string'],
        ]);

        $challenge = $this->challenges->consume($data['token'], WebauthnChallenge::TYPE_AUTHENTICATION);

        if ($challenge === null) {
            throw WebauthnException::invalidChallenge();
        }

        $credential = $this->webauthn->verifyAuthentication($challenge->options, json_encode($data['response']));
        $user = $credential->user;

        if (isset($data['two_factor_token'])) {
            $pending = $this->twoFactorChallenges->resolve($data['two_factor_token']);

            if ($pending === null || $pending->id !== $user->id) {
                return response()->json(['message' => 'Invalid or expired token.'], 401);
            }

            $this->twoFactorChallenges->consume($data['two_factor_token']);
        }

        return $this->issueSession($user, $request);
    }
}
