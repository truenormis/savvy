<?php

namespace App\Services\Sso\Presets;

use App\Enums\SsoPreset;
use App\Enums\SsoProtocol;
use App\Models\IdentityProvider;

interface PresetContract
{
    public function key(): SsoPreset;

    public function label(): string;

    public function protocol(): SsoProtocol;

    /**
     * Form field descriptors driving the admin "add provider" UI.
     *
     * @return array<int, array{key:string,label:string,type:string,required:bool,secret?:bool,group:string,placeholder?:string,help?:string}>
     */
    public function fields(): array;

    /**
     * Default claim/attribute mappings (dot-paths) for this provider.
     *
     * @return array<string, string|null>
     */
    public function defaultClaimMappings(): array;

    /**
     * Resolve the admin-supplied raw config into the concrete connection
     * settings the matching connector consumes (endpoints, scopes, certs…).
     *
     * @return array<string, mixed>
     */
    public function resolveConnection(IdentityProvider $provider): array;

    /**
     * Sanitised descriptor (no secrets) for the frontend catalog.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
