<?php

declare(strict_types=1);

namespace Octava\Integration\Sameday;

use Dot\DependencyInjection\Factory\AttributedServiceFactory;
use Laminas\ModuleManager\Feature\ServiceProviderInterface;
use Octava\Integration\Sameday\Factory\Listener\PreprocessingTaskListenerFactory;
use Octava\Integration\Sameday\Factory\V2\SamedayClientFactory;
use Octava\Integration\Sameday\Listener\PreprocessingTaskListener;
use Octava\Integration\Sameday\Service\Calculation;
use Octava\Integration\Sameday\Service\Labels;
use Octava\Integration\Sameday\Service\Payments;
use Octava\Integration\Sameday\Service\Settings;
use Octava\Integration\Sameday\Service\Shipment;
use Octava\Integration\Sameday\Service\V2\SamedayClient;
use Octava\Integration\Sameday\Service\V2\Shipment as ShipmentV2;
use Orderadmin\DeliveryServices\Factory\DeliveryServiceV2Factory;
use Orderadmin\Integrations\Factory\IntegrationV2SettingsFactory;

class Module implements ServiceProviderInterface
{
    const INTEGRATION_ID = 'sameday';

    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function getServiceConfig()
    {
        return [
            'factories' => [
                Calculation::class                     => DeliveryServiceV2Factory::class,
                Service\Loader\ServicePoints::class    => DeliveryServiceV2Factory::class,
                Service\Loader\DeliveryRequests::class => AttributedServiceFactory::class,
                PreprocessingTaskListener::class       => PreprocessingTaskListenerFactory::class,
                Shipment::class                        => DeliveryServiceV2Factory::class,
                ShipmentV2::class                      => AttributedServiceFactory::class,
                SamedayClient::class                   => SamedayClientFactory::class,
                Settings::class                        => IntegrationV2SettingsFactory::class,
                Labels::class                          => AttributedServiceFactory::class,
                Payments::class                        => AttributedServiceFactory::class,
            ],
        ];
    }
}
