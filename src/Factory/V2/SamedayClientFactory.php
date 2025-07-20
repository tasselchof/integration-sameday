<?php

declare(strict_types=1);

namespace Octava\Integration\Sameday\Factory\V2;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Octava\Integration\Sameday\Service\V2\SamedayClient;
use Psr\Container\ContainerInterface;

class SamedayClientFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): SamedayClient
    {
        // For now, create a client with default settings
        // In a real implementation, you would get settings from the integration
        $username = 'default_username';
        $password = 'default_password';
        $apiUrl   = 'https://api.sameday.ro';
        $testMode = true; // Use test mode by default

        return new SamedayClient($username, $password, $apiUrl, $testMode);
    }
}
