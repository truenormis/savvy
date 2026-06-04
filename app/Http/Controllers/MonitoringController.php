<?php

namespace App\Http\Controllers;

use App\Services\Monitoring\StorageMonitor;
use App\Services\Monitoring\SystemResourceMonitor;
use Illuminate\Http\JsonResponse;

class MonitoringController extends Controller
{
    public function __construct(
        private StorageMonitor $storage,
        private SystemResourceMonitor $resources
    ) {}

    public function storage(): JsonResponse
    {
        return response()->json($this->storage->snapshot());
    }

    public function resources(): JsonResponse
    {
        return response()->json($this->resources->snapshot());
    }
}
