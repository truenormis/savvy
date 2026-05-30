<?php

namespace App\Http\Controllers;

use App\Enums\SsoProtocol;
use App\Exceptions\SsoException;
use App\Http\Requests\IdentityProvider\StoreIdentityProviderRequest;
use App\Http\Requests\IdentityProvider\UpdateIdentityProviderRequest;
use App\Http\Resources\IdentityProviderResource;
use App\Models\IdentityProvider;
use App\Services\Sso\Connectors\SamlConnector;
use App\Services\Sso\IdentityProviderService;
use App\Services\Sso\JwksService;
use App\Services\Sso\Presets\PresetRegistry;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IdentityProviderController extends Controller
{
    public function __construct(
        private IdentityProviderService $service,
        private PresetRegistry $presets,
        private JwksService $jwks,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return IdentityProviderResource::collection($this->service->all());
    }

    public function presets(): JsonResponse
    {
        return response()->json($this->presets->catalog());
    }

    public function store(StoreIdentityProviderRequest $request): JsonResponse
    {
        try {
            $provider = $this->service->create($request->validated());

            return response()->json(new IdentityProviderResource($provider), 201);
        } catch (SsoException $e) {
            return response()->json(['message' => $e->getMessage(), 'error' => $e->errorCode], $e->status);
        }
    }

    public function show(IdentityProvider $identityProvider): IdentityProviderResource
    {
        return new IdentityProviderResource($identityProvider);
    }

    public function update(UpdateIdentityProviderRequest $request, IdentityProvider $identityProvider): JsonResponse
    {
        try {
            $provider = $this->service->update($identityProvider, $request->validated());

            return response()->json(new IdentityProviderResource($provider));
        } catch (SsoException $e) {
            return response()->json(['message' => $e->getMessage(), 'error' => $e->errorCode], $e->status);
        }
    }

    public function destroy(IdentityProvider $identityProvider): JsonResponse
    {
        try {
            $this->service->delete($identityProvider);

            return response()->json(null, 204);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function test(IdentityProvider $identityProvider): JsonResponse
    {
        try {
            match ($identityProvider->protocol) {
                SsoProtocol::Oidc => $this->jwks->discover(
                    $this->presets->get($identityProvider->preset)->resolveConnection($identityProvider)['discovery_url']
                ),
                SsoProtocol::Saml => app(SamlConnector::class)->metadata($identityProvider),
                SsoProtocol::OAuth2 => null,
            };

            return response()->json(['status' => 'ok']);
        } catch (SsoException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'error' => $e->errorCode], $e->status);
        }
    }
}
