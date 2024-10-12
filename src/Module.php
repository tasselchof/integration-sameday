<?php

namespace Octava\Integrations\Sameday;

use Laminas\ModuleManager\Feature\ServiceProviderInterface;
use Octava\Integrations\Sameday\Service\Calculation;
use Octava\Integrations\Sameday\Service\Labels;
use Octava\Integrations\Sameday\Service\Loader\ServicePoints;
use Octava\Integrations\Sameday\Service\Loader\Services;
use Octava\Integrations\Sameday\Service\Shipment;
use Orderadmin\DeliveryServices\Factory\DeliveryServiceV2Factory;

class Module implements ServiceProviderInterface
{
    const DELIVERY_SERVICE = 'sameday';

    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function getServiceConfig()
    {
        return [
            'factories' => [
                Services::class            => DeliveryServiceV2Factory::class,
                ServicePoints::class       => DeliveryServiceV2Factory::class,
                Shipment::class            => DeliveryServiceV2Factory::class,
                Calculation::class         => DeliveryServiceV2Factory::class,
                Labels::class              => DeliveryServiceV2Factory::class,
            ],
        ];
    }
}
