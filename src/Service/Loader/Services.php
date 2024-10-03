<?php
/**
 * Created by PhpStorm.
 * User: IqCreative - Acho
 * Date: 7/17/2019
 * Time: 10:13 AM
 */

namespace Octava\Integrations\Sameday\Service\Loader;

use Octava\Integrations\Sameday\Service\Integration;
use Octava\Integrations\Sameday\Service\SamedayClient;
use Orderadmin\Application\Model\LoggerAwareInterface;
use Orderadmin\Application\Model\Manager\ObjectManagerAwareInterface;
use Orderadmin\Application\Traits\LoggerAwareTrait;
use Orderadmin\Application\Traits\ObjectManagerAwareTrait;
use Orderadmin\DeliveryServices\Model\Feature\V2\Loader\RatesProviderInterface;
use Orderadmin\DeliveryServices\Entity\Rate;
use Orderadmin\DeliveryServices\Traits\DeliveryServiceV2Trait;
use Orderadmin\DeliveryServices\Traits\Feature\PaginatorTrait;
use Orderadmin\Locations\Traits\LocalityManagerAwareTrait;
use Sameday\Exceptions\SamedayAuthenticationException;
use Sameday\Requests\SamedayGetServicesRequest;
use Sameday\Sameday;

class Services extends Integration implements
    ObjectManagerAwareInterface,
    LoggerAwareInterface,
    RatesProviderInterface
{
    use DeliveryServiceV2Trait,
        ObjectManagerAwareTrait,
        LoggerAwareTrait,
        LocalityManagerAwareTrait,
        PaginatorTrait;

    protected array $currentItems = [];

    public function count()
    {
        return count($this->currentItems);
    }

    public function loadElements(array $criteria = [], int $page = 1): array
    {
        $settings = $this->getIntegration()->getSettings();
        $samedayClient = new SamedayClient($settings['username'], $settings['password']);
        $sameday = new Sameday($samedayClient);
        $res = $sameday->getServices(new SamedayGetServicesRequest);
        $rates = [];
        if (! empty($res->getServices())) {
            foreach ($res->getServices() as $service) {
                $rates[] = [
                    'name'  => $service->getName(),
                    'extId' => $service->getCode(),
                    'type'  => Rate::TYPE_SIMPLE,
                    'raw'   => $service,
                ];
            }
        }

        return $rates;
    }
}
