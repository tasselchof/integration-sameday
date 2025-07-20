<?php

declare(strict_types=1);

namespace Octava\Integration\Sameday\Service\V2;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Dot\DependencyInjection\Attribute\Inject;
use Orderadmin\DeliveryServices\Model\Feature\V2\ShipmentProviderInterface;
use Orderadmin\DeliveryServices\Traits\Feature\ConnectionAwareTrait;
use Psr\Log\LoggerInterface;

class Shipment implements ShipmentProviderInterface
{
    use ConnectionAwareTrait;

    #[Inject(
        EntityManagerInterface::class,
        EntityManager::class,
        LoggerInterface::class
    )]
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected EntityManagerInterface $objectManager,
        protected LoggerInterface $logger
    ) {
    }

    public function createShipment(): array
    {
        // Placeholder implementation for V2 interface
        return [
            'shipment_id'     => '',
            'tracking_number' => '',
            'status'          => 'created',
        ];
    }

    public function prepareTask(\Orderadmin\DeliveryServices\Entity\Processing\Task $task): array
    {
        // Placeholder implementation for V2 interface
        return [
            'task_id' => '',
            'status'  => 'prepared',
        ];
    }
}
