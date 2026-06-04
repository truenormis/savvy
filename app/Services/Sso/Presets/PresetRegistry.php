<?php

namespace App\Services\Sso\Presets;

use App\Enums\SsoPreset;
use App\Exceptions\SsoException;

class PresetRegistry
{
    /**
     * @var array<string, PresetContract>
     */
    private array $instances = [];

    /**
     * @param  iterable<PresetContract>  $presets
     */
    public function __construct(iterable $presets)
    {
        foreach ($presets as $preset) {
            $this->instances[$preset->key()->value] = $preset;
        }
    }

    public function get(SsoPreset|string $preset): PresetContract
    {
        $key = $preset instanceof SsoPreset ? $preset->value : $preset;

        return $this->instances[$key]
            ?? throw SsoException::make('unknown_preset', "Unknown SSO preset: {$key}", 404);
    }

    /**
     * @return array<string, PresetContract>
     */
    public function all(): array
    {
        return $this->instances;
    }

    /**
     * Sanitised catalog (no secrets) for the admin UI.
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalog(): array
    {
        return array_values(array_map(
            fn (PresetContract $preset) => $preset->toArray(),
            $this->instances,
        ));
    }
}
