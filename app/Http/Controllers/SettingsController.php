<?php

namespace App\Http\Controllers;

use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(protected SettingsService $settings)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->settings->all());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'auto_update_currencies' => 'sometimes|boolean',
        ]);

        foreach ($data as $key => $value) {
            $this->settings->set($key, $value);
        }

        return response()->json($this->settings->all());
    }
}
