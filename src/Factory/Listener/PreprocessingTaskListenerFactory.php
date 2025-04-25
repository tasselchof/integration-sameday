<?php

declare(strict_types=1);

namespace Octava\Integration\Sameday\Factory\Listener;

use Doctrine\ORM\EntityManager;
use Orderadmin\Accounts\Service\AccountsService;
use Orderadmin\Application\Model\Manager\ServiceManagerAwareInterface;
use Orderadmin\DeliveryServices\Service\DeliveryServices;
use Orderadmin\DeliveryServices\Service\DeliveryServicesRequestsService;
use Octava\Integration\Sameday\Listener\PreprocessingTaskListener;
use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PreprocessingTaskListenerFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        $serviceObj = new PreprocessingTaskListener();

        if ($serviceObj instanceof ServiceManagerAwareInterface) {
            $serviceObj->setServiceManager($container);
        }

        $objectManager = $container->get(EntityManager::class);
        $serviceObj->setObjectManager($objectManager);

        $deliveryRequestManager = $container->get(DeliveryServicesRequestsService::class);
        $serviceObj->setDeliveryRequestManager($deliveryRequestManager);

        $accountsManager = $container->get(AccountsService::class);
        $serviceObj->setAccountManager($accountsManager);

        $deliveryServiceManager = $container->get(DeliveryServices::class);
        $serviceObj->setDeliveryServiceManager($deliveryServiceManager);



        return $serviceObj;
    }
}
