<?php

declare(strict_types=1);

namespace Octava\Integration\Sameday\Service\Loader;

use Octava\Integration\Sameday\Service\Integration;
use Octava\Integration\Sameday\Service\SamedayClient;
use Orderadmin\Application\Model\LoggerAwareInterface;
use Orderadmin\Application\Model\Manager\ObjectManagerAwareInterface;
use Orderadmin\Application\Traits\LoggerAwareTrait;
use Orderadmin\Application\Traits\ObjectManagerAwareTrait;
use Orderadmin\DeliveryServices\Entity\Rate;
use Orderadmin\DeliveryServices\Model\Feature\V2\Loader\RatesProviderInterface;
use Orderadmin\DeliveryServices\Traits\DeliveryServiceV2Trait;
use Orderadmin\DeliveryServices\Traits\Feature\PaginatorTrait;
use Orderadmin\Locations\Traits\LocalityManagerAwareTrait;
use Sameday\Requests\SamedayGetServicesRequest;
use Sameday\Sameday;

use function count;

class Services extends Integration implements
    ObjectManagerAwareInterface,
    LoggerAwareInterface,
    RatesProviderInterface
{
    use DeliveryServiceV2Trait;
    use LocalityManagerAwareTrait;
    use LoggerAwareTrait;
    use ObjectManagerAwareTrait;
    use PaginatorTrait;

    protected array $currentItems = [];

    public function count()
    {
        return count($this->currentItems);
    }

    public function loadElements(array $criteria = [], int $page = 1): array
    {
        $settings      = $this->getIntegration()->getSettings();
        $samedayClient = new SamedayClient($settings['username'], $settings['password']);
        $sameday       = new Sameday($samedayClient);
        $res           = $sameday->getServices(new SamedayGetServicesRequest());
        $rates         = [];
        if (! empty($res->getServices())) {
            foreach ($res->getServices() as $service) {
                $rates[] = [
                    'name'  => $service->getName(),
                    'extId' => $service->getId(),
                    'type'  => Rate::TYPE_SERVICE_POINT,
                    'raw'   => $service,
                ];
            }
        }

        return $rates;
    }
}
