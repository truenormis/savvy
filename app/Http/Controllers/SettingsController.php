<?php

namespace App\Http\Controllers;

use App\Models\IdentityProvider;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(protected SettingsService $settings) {}

    public function index(): JsonResponse
    {
        return response()->json($this->settings->all());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'auto_update_currencies' => 'sometimes|boolean',
            'sso_allow_signup' => 'sometimes|boolean',
            'password_login_enabled' => 'sometimes|boolean',
            'sso_require_verified_email' => 'sometimes|boolean',
        ]);

        if (($data['password_login_enabled'] ?? true) === false && ! IdentityProvider::where('enabled', true)->exists()) {
            return response()->json([
                'message' => 'Enable at least one SSO provider before turning off password sign-in.',
                'error' => 'sso_required',
            ], 422);
        }

        foreach ($data as $key => $value) {
            $this->settings->set($key, $value);
        }

        return response()->json($this->settings->all());
    }
}
