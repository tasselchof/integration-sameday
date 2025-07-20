<?php

declare(strict_types=1);

namespace Octava\Integration\Sameday\Service\Loader;

use Orderadmin\DeliveryServices\Model\Feature\V2\Loader\DeliveryRequestsProviderInterface;
use Orderadmin\DeliveryServices\Traits\Feature\ConnectionAwareTrait;
use Orderadmin\DeliveryServices\Traits\Feature\PaginatorTrait;

use function count;

class DeliveryRequests implements DeliveryRequestsProviderInterface
{
    use ConnectionAwareTrait;
    use PaginatorTrait;

    protected array $currentItems = [];

    public function loadElements(array $criteria = [], int $page = 1): array
    {
        // Implementation for loading delivery requests
        // This would typically query the database for delivery requests
        // and return them in the expected format
        return [];
    }

    public function count(): int
    {
        return count($this->currentItems);
    }
}
