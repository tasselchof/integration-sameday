<?php

declare(strict_types=1);

namespace Octava\Integration\Sameday\Service;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Dot\DependencyInjection\Attribute\Inject;
use Orderadmin\DeliveryServices\Service\Payment\PaymentProviderInterface;
use Orderadmin\DeliveryServices\Traits\Feature\ConnectionAwareTrait;
use Psr\Log\LoggerInterface;

class Payments implements PaymentProviderInterface
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

    public function processPayment(array $data): array
    {
        // Implementation for processing payments
        // This would handle payment processing for Sameday shipments
        return [
            'success'        => true,
            'transaction_id' => $data['transaction_id'] ?? null,
            'amount'         => $data['amount'] ?? 0,
            'currency'       => $data['currency'] ?? 'RON',
        ];
    }
}
