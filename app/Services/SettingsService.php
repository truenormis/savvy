<?php

namespace App\Services;

use App\Models\Setting;

class SettingsService
{
    protected array $cache = [];

    protected array $defaults;

    protected bool $loaded = false;

    public function __construct()
    {
        $this->defaults = config('settings', []);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->loadAll();

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        return $this->defaults[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $this->serialize($value)]
        );

        $this->cache[$key] = $value;
    }

    public function all(): array
    {
        $this->loadAll();

        return array_merge($this->defaults, $this->cache);
    }

    public function getDefault(string $key, mixed $default = null): mixed
    {
        return $this->defaults[$key] ?? $default;
    }

    protected function loadAll(): void
    {
        if ($this->loaded) {
            return;
        }

        $settings = Setting::all();

        foreach ($settings as $setting) {
            $this->cache[$setting->key] = $this->unserialize($setting->value);
        }

        $this->loaded = true;
    }

    protected function serialize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value);
    }

    protected function unserialize(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
