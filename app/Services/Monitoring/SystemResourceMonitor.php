<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemResourceMonitor
{
    private const UNLIMITED = 9223372036854771712;

    public function snapshot(): array
    {
        $cores = $this->cpuCores();

        return [
            'cpu' => $this->cpu($cores),
            'memory' => $this->memory(),
            'process' => $this->process(),
            'queue' => $this->queue(),
            'runtime' => $this->runtime(),
        ];
    }

    private function cpu(?float $cores): array
    {
        $load = \function_exists('sys_getloadavg') ? sys_getloadavg() : null;
        $load = \is_array($load) ? array_map(fn ($v) => round((float) $v, 2), array_values($load)) : null;

        $percent = null;

        if ($load !== null && $cores !== null && $cores > 0) {
            $percent = round(min(100, $load[0] / $cores * 100), 1);
        }

        return [
            'cores' => $cores,
            'load' => $load,
            'load_percent' => $percent,
        ];
    }

    private function memory(): array
    {
        $stats = $this->cgroupMemory() ?? $this->hostMemory();

        if ($stats === null) {
            return [
                'total_bytes' => null,
                'used_bytes' => null,
                'free_bytes' => null,
                'used_percent' => null,
                'source' => null,
            ];
        }

        [$total, $used, $source] = $stats;
        $free = max(0, $total - $used);

        return [
            'total_bytes' => $total,
            'used_bytes' => $used,
            'free_bytes' => $free,
            'used_percent' => $total > 0 ? round($used / $total * 100, 1) : null,
            'source' => $source,
        ];
    }

    private function process(): array
    {
        $limit = $this->parseBytes((string) \ini_get('memory_limit'));

        return [
            'memory_bytes' => memory_get_usage(true),
            'peak_bytes' => memory_get_peak_usage(true),
            'limit_bytes' => $limit !== null && $limit > 0 ? $limit : null,
        ];
    }

    private function queue(): array
    {
        return [
            'pending' => $this->countTable($this->queueConnection(), 'jobs'),
            'reserved' => $this->countReserved(),
            'failed' => $this->countTable($this->failedConnection(), 'failed_jobs'),
        ];
    }

    private function runtime(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'uptime_seconds' => $this->uptime(),
        ];
    }

    private function cpuCores(): ?float
    {
        $quota = $this->cgroupCpuQuota();

        if ($quota !== null && $quota > 0) {
            return round($quota, 2);
        }

        $cpuinfo = $this->read('/proc/cpuinfo');

        if ($cpuinfo !== null) {
            $count = preg_match_all('/^processor\s*:/m', $cpuinfo);

            if ($count > 0) {
                return (float) $count;
            }
        }

        return null;
    }

    private function cgroupCpuQuota(): ?float
    {
        $v2 = $this->read('/sys/fs/cgroup/cpu.max');

        if ($v2 !== null) {
            $parts = preg_split('/\s+/', trim($v2));

            if (\count($parts) === 2 && $parts[0] !== 'max' && (float) $parts[1] > 0) {
                return (float) $parts[0] / (float) $parts[1];
            }
        }

        $quota = $this->read('/sys/fs/cgroup/cpu/cpu.cfs_quota_us');
        $period = $this->read('/sys/fs/cgroup/cpu/cpu.cfs_period_us');

        if ($quota !== null && $period !== null) {
            $quota = (float) trim($quota);
            $period = (float) trim($period);

            if ($quota > 0 && $period > 0) {
                return $quota / $period;
            }
        }

        return null;
    }

    private function cgroupMemory(): ?array
    {
        $maxRaw = $this->read('/sys/fs/cgroup/memory.max');
        $currentRaw = $this->read('/sys/fs/cgroup/memory.current');

        if ($maxRaw !== null && $currentRaw !== null) {
            $max = trim($maxRaw);
            $current = (int) trim($currentRaw);

            if ($max !== 'max') {
                $limit = (int) $max;

                if ($limit > 0 && $limit < self::UNLIMITED) {
                    return [$limit, min($current, $limit), 'cgroup'];
                }
            }
        }

        $limitRaw = $this->read('/sys/fs/cgroup/memory/memory.limit_in_bytes');
        $usageRaw = $this->read('/sys/fs/cgroup/memory/memory.usage_in_bytes');

        if ($limitRaw !== null && $usageRaw !== null) {
            $limit = (int) trim($limitRaw);
            $usage = (int) trim($usageRaw);

            if ($limit > 0 && $limit < self::UNLIMITED) {
                return [$limit, min($usage, $limit), 'cgroup'];
            }
        }

        return null;
    }

    private function hostMemory(): ?array
    {
        $meminfo = $this->read('/proc/meminfo');

        if ($meminfo === null) {
            return null;
        }

        $total = $this->meminfoValue($meminfo, 'MemTotal');
        $available = $this->meminfoValue($meminfo, 'MemAvailable');

        if ($total === null) {
            return null;
        }

        if ($available === null) {
            $free = $this->meminfoValue($meminfo, 'MemFree') ?? 0;
            $buffers = $this->meminfoValue($meminfo, 'Buffers') ?? 0;
            $cached = $this->meminfoValue($meminfo, 'Cached') ?? 0;
            $available = $free + $buffers + $cached;
        }

        return [$total, max(0, $total - $available), 'host'];
    }

    private function meminfoValue(string $meminfo, string $key): ?int
    {
        if (preg_match('/^'.preg_quote($key, '/').':\s+(\d+)\s*kB/m', $meminfo, $m)) {
            return (int) $m[1] * 1024;
        }

        return null;
    }

    private function uptime(): ?int
    {
        $uptime = $this->read('/proc/uptime');

        if ($uptime === null) {
            return null;
        }

        $seconds = (float) strtok(trim($uptime), ' ');

        return $seconds > 0 ? (int) $seconds : null;
    }

    private function countReserved(): ?int
    {
        $connection = $this->queueConnection();

        if (! $this->hasTable($connection, 'jobs')) {
            return null;
        }

        try {
            return DB::connection($connection)->table('jobs')->whereNotNull('reserved_at')->count();
        } catch (Throwable) {
            return null;
        }
    }

    private function countTable(?string $connection, string $table): ?int
    {
        if (! $this->hasTable($connection, $table)) {
            return null;
        }

        try {
            return DB::connection($connection)->table($table)->count();
        } catch (Throwable) {
            return null;
        }
    }

    private function hasTable(?string $connection, string $table): bool
    {
        try {
            return Schema::connection($connection)->hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    private function queueConnection(): ?string
    {
        return config('queue.connections.database.connection') ?: config('database.default');
    }

    private function failedConnection(): ?string
    {
        return config('queue.failed.database') ?: config('database.default');
    }

    private function read(string $path): ?string
    {
        if (! @is_readable($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        return $contents === false ? null : $contents;
    }

    private function parseBytes(string $value): ?int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return null;
        }

        $unit = strtolower($value[\strlen($value) - 1]);
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
