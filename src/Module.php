<?php

namespace Octava\Integration\Sameday;

use Laminas\ModuleManager\Feature\ServiceProviderInterface;
use Octava\Integration\Sameday\Service\Calculation;
use Octava\Integration\Sameday\Service\Labels;
use Octava\Integration\Sameday\Service\Loader\ServicePoints;
use Octava\Integration\Sameday\Service\Loader\Services;
use Octava\Integration\Sameday\Service\Shipment;
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
