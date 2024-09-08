<?php
/**
 * Created by PhpStorm.
 * User: IqCreative - Acho
 * Date: 7/17/2019
 * Time: 10:13 AM
 */

namespace Octava\Integrations\Sameday\Service\DeliveryServices\Loader;

use Octava\Integrations\Sameday\Service\Integration;
use Octava\Integrations\Sameday\Traits\DeliveryServicesTrait;
use Orderadmin\DeliveryServices\Entity\DeliveryRequest;
use Orderadmin\DeliveryServices\Exception\DeliveryRequestException;
use Orderadmin\DeliveryServices\Exception\DeliveryServiceException;
use Orderadmin\DeliveryServices\Model\Feature\V2\Loader\DeliveryRequestsProviderInterface;
use Orderadmin\DeliveryServices\Traits\Feature\PaginatorTrait;

class DeliveryRequests extends Integration implements DeliveryRequestsProviderInterface
{
    use PaginatorTrait, DeliveryServicesTrait;

    protected array $currentItems = [];

    public function count()
    {
        return count($this->currentItems);
    }

    protected function getStatusList(): array
    {
        $statusList = [];

        return $statusList;
    }

    public function loadElements(array $criteria = [], int $page = 1): array
    {
        if (empty($criteria['integration'])) {
            throw new DeliveryRequestException('Integration not set');
        }

        /** @var DeliveryRequest $deliveryRequest */
        $deliveryRequest = $this->getObjectManager()->getRepository(
            DeliveryRequest::class
        )->find($criteria['id']);

        $this->getLogger()->info(
            sprintf(
                'Searching for statuses for delivery request %s',
                $deliveryRequest->getId()
            )
        );

        if (empty($deliveryRequest->getIntegration())) {
            throw new DeliveryServiceException(
                sprintf(
                    'Integration not set for delivery request %s.',
                    $deliveryRequest->getId()
                )
            );
        }

        $deliveryService = $this->getDeliveryRequestsService();

        $exportResult = $deliveryService->requestShipment($deliveryRequest);

        var_dump($exportResult);
        die();

        $updateData = [];

        if (! empty($result)) {
            $newStatus = $result['entity']['statuses'][0]['code'];
            if (empty($this->getStatusList()[$newStatus])) {
                $this->getLogger()->warn(
                    sprintf('Status "%s" is missing value', $newStatus)
                );

                return [];
            }

            $state = $this->getStatusList()[$newStatus];

            if ($state) {
                if ($deliveryRequest->getState() != $state) {
                    $this->getLogger()->info(
                        sprintf(
                            'Delivery request %s state will be updated from %s to %s',
                            $deliveryRequest->getId(),
                            $deliveryRequest->getState(),
                            $state
                        )
                    );

                    $updateData['state'] = $state;
                    $updateData['tracking'] = $result;
                } else {
                    $this->getLogger()->info(
                        sprintf(
                            'Delivery request %s state not changed - %s',
                            $deliveryRequest->getId(),
                            $state
                        )
                    );
                }
            }

            if (! empty($updateData)) {
                return [
                    $updateData,
                ];
            }
        }

        return [];
    }
}
