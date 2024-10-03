<?php

namespace Octava\Integrations\Sameday;

use Laminas\ModuleManager\Feature\ServiceProviderInterface;
use Octava\Integrations\Sameday\Factory\Listener\SourceListenerFactory;
use Octava\Integrations\Sameday\Listener\SourceListener;
use Octava\Integrations\Sameday\Service\Calculation;
use Octava\Integrations\Sameday\Service\DeliveryRequests;
use Octava\Integrations\Sameday\Service\Loader\ServicePoints;
use Octava\Integrations\Sameday\Service\Loader\Services;
use Octava\Integrations\Sameday\Service\Settings;
use Octava\Integrations\Sameday\Service\SourcesServices;
use Orderadmin\DeliveryServices\Factory\DeliveryServiceV2Factory;

class Module implements ServiceProviderInterface
{
    const DELIVERY_SERVICE = 'sameday';

    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

//    public function getAutoloaderConfig()
//    {
//        return [
//            'Laminas\ApiTools\Autoloader' => [
//                'namespaces' => [
//                    __NAMESPACE__ . '\Api' => __DIR__ . '/../Api/src',
//                ],
//            ],
//        ];
//    }

//    public function onBootstrap(EventInterface $e): void
//    {
//        $application = $e->getApplication();
//        $sm = $application->getServiceManager();
//
//
//        /** @var OrderadminService $orderadminManager */
//        $orderadminManager = $sm->get(OrderadminService::class);
//
//        $sourceListener = $sm->get(SourceListener::class);
//        $sourceListener->attach($orderadminManager->getEventManager());
//    }

    public function getServiceConfig()
    {
        return [
            'factories' => [
                Service\Integration::class => DeliveryServiceV2Factory::class,
                Services::class            => DeliveryServiceV2Factory::class,
                ServicePoints::class       => DeliveryServiceV2Factory::class,
//                DeliveryServices\Shipment::class    => DeliveryServiceV2Factory::class,
//                DeliveryServices\Integration::class => DeliveryServiceV2Factory::class,
//                DeliveryServices\Calculation::class => DeliveryServiceV2Factory::class,
//                DeliveryServices\Labels::class      => DeliveryServiceV2Factory::class,
//                Loader\DeliveryRequests::class      => DeliveryServiceV2Factory::class,
//                Settings::class                     => IntegrationV2SettingsFactory::class,
//                Calculation::class                  => IntegrationV2Factory::class,
//                SourceListener::class               => SourceListenerFactory::class,
//                PreprocessingTaskListener::class    => PreprocessingTaskListenerFactory::class,
//                SourcesServices::class              => IntegrationV2Factory::class
            ],
        ];
    }
}
