<?php
/**
 * Created by PhpStorm.
 * User: mtodo
 * Date: 31.07.19
 * Time: 15:30
 */

namespace Octava\Integrations\Sameday\Service\DeliveryServices;

use Orderadmin\DeliveryServices\Entity\DeliveryRequest;
use Orderadmin\DeliveryServices\Model\Feature\V2\CalculationProviderInterface;
use Orderadmin\DeliveryServices\Traits\DeliveryServiceV2Trait;
use Orderadmin\Integrations\Entity\AbstractSource;
use Octava\Integrations\Sameday\Exception\SamedayException;
use Octava\Integrations\Sameday\Service\Calculation as IntegrationCalculation;

class Calculation implements CalculationProviderInterface
{
    use DeliveryServiceV2Trait;

    public function calculateShipment(
        DeliveryRequest $deliveryRequest,
        float $weight,
        float $x = 0,
        float $y = 0,
        float $z = 0,
        array $data = []
    ): array {
        $integrationConfig = $this->getAppConfig();

        $sourceId = $integrationConfig['source'];
        $source = $this->getObjectManager()->getRepository(
            AbstractSource::class
        )->find(
            $sourceId
        );
        if (empty($source)) {
            throw new SamedayException(
                sprintf('Source "%s" not found', $sourceId)
            );
        }

        /** @var IntegrationCalculation $calculationService */
        $calculationService = $this->getServiceManager()->build(
            IntegrationCalculation::class,
            [
                'source' => $source,
                'integration' => $this->getIntegration(),
            ]
        );

        $result = $calculationService->calculateShipment(
            $this->getDeliveryService(),
            $deliveryRequest,
            $this->getIntegration(),
            $weight,
            $x,
            $y,
            $z,
            $data
        );

        return $result;
    }
}
