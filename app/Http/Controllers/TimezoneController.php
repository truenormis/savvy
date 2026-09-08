<?php

namespace App\Http\Controllers;

use App\Support\AppTime;
use Illuminate\Http\JsonResponse;

class TimezoneController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'timezones' => AppTime::available(),
            'current' => AppTime::timezone(),
        ]);
    }
}
