<?php

namespace App\Services\Sso;

use App\Exceptions\SsoException;
use App\Models\IdentityProvider;
use App\Models\User;
use App\Services\Sso\Presets\PresetContract;
use App\Services\Sso\Presets\PresetRegistry;
use DomainException;
use Illuminate\Database\Eloquent\Collection;

class IdentityProviderService
{
    public function __construct(
        private PresetRegistry $presets,
    ) {}

    public function all(): Collection
    {
        return IdentityProvider::orderBy('sort_order')->orderBy('id')->get();
    }

    public function create(array $data): IdentityProvider
    {
        $preset = $this->presets->get($data['preset']);
        [$config, $secrets] = $this->assembleFields($preset, $data['fields'] ?? [], null);
        $this->assertRequiredFields($preset, $config, $secrets);

        return IdentityProvider::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'protocol' => $preset->protocol(),
            'preset' => $preset->key(),
            'enabled' => $data['enabled'] ?? false,
            'sort_order' => $data['sort_order'] ?? 0,
            'config' => $config,
            'secrets' => $secrets,
            'claim_mappings' => $data['claim_mappings'] ?? $preset->defaultClaimMappings(),
            'role_mapping' => $data['role_mapping'] ?? [],
            'default_role' => $data['default_role'] ?? 'read-only',
            'allow_jit' => $data['allow_jit'] ?? true,
            'sync_role_on_login' => $data['sync_role_on_login'] ?? false,
            'link_by_email' => $data['link_by_email'] ?? true,
        ]);
    }

    public function update(IdentityProvider $provider, array $data): IdentityProvider
    {
        $preset = $this->presets->get($provider->preset);

        $attributes = collect($data)->only([
            'name', 'slug', 'enabled', 'sort_order', 'claim_mappings', 'role_mapping',
            'default_role', 'allow_jit', 'sync_role_on_login', 'link_by_email',
        ])->toArray();

        if (array_key_exists('fields', $data)) {
            [$config, $secrets] = $this->assembleFields($preset, $data['fields'], $provider);
            $this->assertRequiredFields($preset, $config, $secrets);
            $attributes['config'] = $config;
            $attributes['secrets'] = $secrets;
        }

        $provider->update($attributes);

        return $provider;
    }

    public function delete(IdentityProvider $provider): void
    {
        $orphans = User::where('is_sso_only', true)
            ->whereHas('identities', fn ($q) => $q->where('identity_provider_id', $provider->id))
            ->whereDoesntHave('identities', fn ($q) => $q->where('identity_provider_id', '!=', $provider->id))
            ->exists();

        if ($orphans) {
            throw new DomainException('Cannot delete: SSO-only users would be left without a way to sign in.');
        }

        $provider->delete();
    }

    /**
     * Split the flat field map into the stored config (connection) and the
     * encrypted secrets bag, preserving existing secrets when left blank.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function assembleFields(PresetContract $preset, array $input, ?IdentityProvider $existing): array
    {
        $config = [];
        $secrets = [];

        foreach ($preset->fields() as $field) {
            $key = $field['key'];
            $value = $input[$key] ?? null;
            $isSecret = $field['secret'] ?? false;

            if ($key === 'scopes' && is_string($value)) {
                $value = array_values(array_filter(preg_split('/\s+/', trim($value)) ?: []));
            }

            if ($isSecret) {
                if (filled($value)) {
                    $secrets[$key] = $value;
                } elseif ($existing && filled($existing->secret($key))) {
                    $secrets[$key] = $existing->secret($key);
                }

                continue;
            }

            $config[$key] = $value;
        }

        return [$config, $secrets];
    }

    private function assertRequiredFields(PresetContract $preset, array $config, array $secrets): void
    {
        foreach ($preset->fields() as $field) {
            if (! ($field['required'] ?? false)) {
                continue;
            }

            $present = ($field['secret'] ?? false)
                ? filled($secrets[$field['key']] ?? null)
                : filled($config[$field['key']] ?? null);

            if (! $present) {
                throw SsoException::make('missing_field', "Missing required field: {$field['label']}.", 422);
            }
        }
    }
}
