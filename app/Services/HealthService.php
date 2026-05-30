<?php

namespace App\Services;

use App\DTOs\HealthReportData;
use App\Services\Health\HealthCheck;

class HealthService
{
    /**
     * @param  iterable<HealthCheck>  $checks
     */
    public function __construct(private readonly iterable $checks) {}

    public function liveness(): HealthReportData
    {
        return HealthReportData::up();
    }

    public function readiness(): HealthReportData
    {
        $results = [];

        foreach ($this->checks as $check) {
            $results[] = $check->run();
        }

        return HealthReportData::fromChecks($results);
    }
}
