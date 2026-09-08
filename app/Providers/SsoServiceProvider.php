<?php

namespace App\Providers;

use App\Services\Sso\ConnectorFactory;
use App\Services\Sso\Connectors\OAuth2Connector;
use App\Services\Sso\Connectors\OidcConnector;
use App\Services\Sso\Connectors\SamlConnector;
use App\Services\Sso\Presets\AuthentikPreset;
use App\Services\Sso\Presets\CustomOidcPreset;
use App\Services\Sso\Presets\CustomSamlPreset;
use App\Services\Sso\Presets\EntraPreset;
use App\Services\Sso\Presets\GithubPreset;
use App\Services\Sso\Presets\GitlabPreset;
use App\Services\Sso\Presets\GooglePreset;
use App\Services\Sso\Presets\KeycloakPreset;
use App\Services\Sso\Presets\OktaPreset;
use App\Services\Sso\Presets\PresetRegistry;
use Illuminate\Support\ServiceProvider;

class SsoServiceProvider extends ServiceProvider
{
    /**
     * Provider presets, indexed by their own SsoPreset key. Adding a provider
     * is a single line here.
     *
     * @var array<class-string>
     */
    private const PRESETS = [
        EntraPreset::class,
        GithubPreset::class,
        GooglePreset::class,
        OktaPreset::class,
        GitlabPreset::class,
        KeycloakPreset::class,
        AuthentikPreset::class,
        CustomOidcPreset::class,
        CustomSamlPreset::class,
    ];

    /**
     * Protocol connectors, indexed by the protocol they declare.
     *
     * @var array<class-string>
     */
    private const CONNECTORS = [
        OidcConnector::class,
        OAuth2Connector::class,
        SamlConnector::class,
    ];

    public function register(): void
    {
        $this->app->tag(self::PRESETS, 'sso.presets');
        $this->app->tag(self::CONNECTORS, 'sso.connectors');

        $this->app->singleton(
            PresetRegistry::class,
            fn ($app) => new PresetRegistry($app->tagged('sso.presets')),
        );

        $this->app->singleton(
            ConnectorFactory::class,
            fn ($app) => new ConnectorFactory($app->tagged('sso.connectors')),
        );
    }
}
