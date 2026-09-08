<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\IdentityProvider
 */
class IdentityProviderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'protocol' => $this->protocol,
            'preset' => $this->preset,
            'enabled' => $this->enabled,
            'sortOrder' => $this->sort_order,
            'config' => $this->config,
            'claimMappings' => $this->claim_mappings,
            'roleMapping' => $this->role_mapping,
            'defaultRole' => $this->default_role,
            'allowJit' => $this->allow_jit,
            'syncRoleOnLogin' => $this->sync_role_on_login,
            'linkByEmail' => $this->link_by_email,
            // Secrets are never serialized; expose only their presence.
            'hasClientSecret' => $this->hasClientSecret(),
            'metadataUrl' => route('sso.metadata', ['slug' => $this->slug]),
            'callbackUrl' => route('sso.callback', ['slug' => $this->slug]),
            'acsUrl' => route('sso.acs', ['slug' => $this->slug]),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
