<?php

namespace App\Services\Sso;

use App\Exceptions\SsoException;
use App\Models\IdentityProvider;
use App\Services\Sso\Connectors\SsoConnector;

class ConnectorFactory
{
    /**
     * @var array<string, SsoConnector>
     */
    private array $connectors = [];

    /**
     * @param  iterable<SsoConnector>  $connectors
     */
    public function __construct(iterable $connectors)
    {
        foreach ($connectors as $connector) {
            $this->connectors[$connector->protocol()->value] = $connector;
        }
    }

    public function for(IdentityProvider $provider): SsoConnector
    {
        return $this->connectors[$provider->protocol->value]
            ?? throw SsoException::make('unsupported_protocol', "No SSO connector for protocol: {$provider->protocol->value}", 500);
    }
}
