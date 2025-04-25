<?php

declare(strict_types=1);

namespace Octava\Integration\Sameday\Listener;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Orderadmin\Accounts\Model\AccountManagerInterface;
use Orderadmin\Accounts\Traits\AccountManagerTrait;
use Orderadmin\Application\Model\Manager\ObjectManagerAwareInterface;
use Orderadmin\Application\Model\Manager\ServiceManagerAwareInterface;
use Orderadmin\Application\Traits\ObjectManagerAwareTrait;
use Orderadmin\Application\Traits\ServiceManagerAwareTrait;
use Orderadmin\DeliveryServices\Model\DeliveryRequestManagerAwareInterface;
use Orderadmin\DeliveryServices\Model\DeliveryServiceManagerInterface;
use Orderadmin\DeliveryServices\Traits\DeliveryRequestManagerAwareTrait;
use Orderadmin\DeliveryServices\Traits\DeliveryServicesServiceTrait;

class PreprocessingTaskListener extends AbstractListenerAggregate implements
    DeliveryRequestManagerAwareInterface,
    ObjectManagerAwareInterface,
    AccountManagerInterface,
    ServiceManagerAwareInterface,
    DeliveryServiceManagerInterface
{
    use DeliveryRequestManagerAwareTrait,
        DeliveryServicesServiceTrait,
        AccountManagerTrait,
        ServiceManagerAwareTrait,
        ObjectManagerAwareTrait;

    public function attach(EventManagerInterface $events, $priority = 1): void
    {
    }
}
