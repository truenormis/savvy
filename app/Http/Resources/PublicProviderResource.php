<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal, unauthenticated view used to render login buttons. The frontend
 * decides how each provider looks based on its preset.
 *
 * @mixin \App\Models\IdentityProvider
 */
class PublicProviderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'preset' => $this->preset,
            'protocol' => $this->protocol,
        ];
    }
}
