<?php

namespace App\Services\Sso\Presets;

use App\Enums\SsoProtocol;

abstract class AbstractPreset implements PresetContract
{
    public function label(): string
    {
        return $this->key()->label();
    }

    public function protocol(): SsoProtocol
    {
        return $this->key()->protocol();
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key()->value,
            'label' => $this->label(),
            'protocol' => $this->protocol()->value,
            'fields' => $this->fields(),
            'default_claim_mappings' => $this->defaultClaimMappings(),
        ];
    }

    /**
     * Standard OAuth2/OIDC client credentials field descriptors.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function clientFields(): array
    {
        return [
            ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'required' => true, 'group' => 'config'],
            ['key' => 'client_secret', 'label' => 'Client secret', 'type' => 'password', 'required' => true, 'secret' => true, 'group' => 'secrets'],
        ];
    }
}
