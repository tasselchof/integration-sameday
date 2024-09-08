<?php
/**
 * Created by PhpStorm.
 * User: tasselchof
 * Date: 16.12.15
 * Time: 2:37
 */

namespace Octava\Integrations\Sameday\Service\DeliveryServices;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Laminas\Http\Request;
use Octava\Integrations\Sameday\Service\Integration;
use Orderadmin\Application\Model\Manager\GearmanManagerAwareInterface;
use Orderadmin\Application\Traits\GearmanManagerAwareTrait;
use Orderadmin\DeliveryServices\Entity\DeliveryRequest;
use Orderadmin\DeliveryServices\Exception\DeliveryServiceException;
use Orderadmin\DeliveryServices\Model\Feature\Queue\TrackingProviderInterface;
use Orderadmin\DeliveryServices\Module;

class Tracking extends Integration implements
    TrackingProviderInterface,
    GearmanManagerAwareInterface
{
    use GearmanManagerAwareTrait;

    public function loadStatesQueue($criteria = [])
    {
        $criteria = [
            'deliveryService' => $this->getDeliveryService(),
            'state'           => [
                DeliveryRequest::STATE_ERROR,
                DeliveryRequest::STATE_SENT,
                DeliveryRequest::STATE_PROCESSING,
                DeliveryRequest::STATE_WAITING,
                DeliveryRequest::STATE_RETURNING,
                DeliveryRequest::STATE_PRE_PROCESSING
            ],
        ];

        /** @var QueryBuilder $deliveryRequestsQuery */
        $deliveryRequestsQuery = $this->getObjectManager()->getRepository(
            DeliveryRequest::class
        )->findByQuery($criteria);

        $deliveryRequestsData = $deliveryRequestsQuery->getQuery()->getResult(
            Query::HYDRATE_ARRAY
        );

        $this->getGearmanManager()->getClient()->setCompleteCallback(
            function (\GearmanTask $task, $context) {
                $result = json_decode($task->data(), true);

                $this->getLogger()->info($result);
            }
        );

        if (count($deliveryRequestsData) == 1) {
            try {
                $this->loadRequestState(
                    [
                        'id' => $deliveryRequestsData[0]['id']
                    ]
                );
            } catch (DeliveryServiceException $e) {
                $this->getLogger()->err($e->getMessage());

                $this->getLogger()->debug($e->getTraceAsString());
            }
        } else {
            foreach ($deliveryRequestsData as $deliveryRequestData) {
                $this->getGearmanManager()->getClient()->addTaskLowBackground(
                    sprintf('%s-queued-task', Module::MODULE_ID),
                    json_encode(
                        [
                            'service' => Tracking::class,
                            'method'  => 'loadRequestState',
                            'params'  => [
                                'id' => $deliveryRequestData['id'],
                            ],
                        ]
                    ),
                    1,
                    sprintf(
                        '%s_%s',
                        DeliveryRequest::class,
                        $deliveryRequestData['id']
                    )
                );
            }

            $this->getGearmanManager()->runTasks();
        }
    }

    public function loadRequestState(array $params): DeliveryRequest
    {
        if (empty($params['id'])) {
            throw new DeliveryServiceException(
                'Delivery request id is required'
            );
        }

        /** @var DeliveryRequest $deliveryRequest */
        $deliveryRequest = $this->getObjectManager()->getRepository(
            DeliveryRequest::class
        )->find($params['id']);

        if (empty($deliveryRequest->getIntegration())) {
            throw new DeliveryServiceException(
                sprintf(
                    'Integration not set for delivery request %s.',
                    $deliveryRequest->getId()
                )
            );
        }

        $settings = $this->getDeliveryServiceManager()->getIntegrationSettings(
            $deliveryRequest->getIntegration()
        );

        if (empty($settings['api-client'])
            || empty($settings['api-key'])
        ) {
            throw new DeliveryServiceException(
                sprintf(
                    'Authorization credentials for API for integration %s must be set',
                    $deliveryRequest->getIntegration()
                        ->getId()
                )
            );
        }

        $this->setKey($this->getAppConfig()['api-key']);
        $this->setClientId($settings['api-client']);

        $data = [
            'posting_number' => $deliveryRequest->getTrackingNumber(),
            'with' => [
                'analytics_data' => true,
                'barcodes' => true,
                'financial_data' => true,
            ]
        ];

        try {
            $request = $this->request(
                self::SERVICE_ORDER_INFO,
                $data,
                Request::METHOD_POST
            )->getResult();

            $result = $request['result'];

            $this->getLogger()->debug($result);

            $statusList = [
//                'awaiting_approve' => 'Ожидает подтверждения',
//                'awaiting_packaging' => 'Ожидает упаковки',
//                'awaiting_deliver' => 'Ожидает отгрузки',
//                'delivering' => 'Доставляется',
//                'driver_pickup' => 'У водителя',
                'delivered' => DeliveryRequest::STATE_COMPLETE,
                'cancelled' => DeliveryRequest::STATE_CANCELLED,
            ];

            if (! empty($result)) {
                $newStatus = $result['status'];
                $state     = $statusList[$newStatus];

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

                        $updateData = [
                            'state' => $state,
                        ];

                        if ($deliveryRequest->getTracking() != $result) {
                            $updateData['tracking'] = $result;
                        }

                        $this->getLogger()->debug($updateData);
                        $this->getDeliveryServiceManager()->saveDeliveryRequest(
                            $updateData,
                            $deliveryRequest
                        );
                    } else {
                        $this->getLogger()->info(
                            sprintf(
                                'Delivery request %s state not changed - %s',
                                $deliveryRequest->getId(),
                                $state
                            )
                        );
                    }
                } else {
                    if (! empty($result['Order']['Msg'])) {
                        $this->getLogger()->info(
                            sprintf(
                                'API returned error: %s',
                                $result['Order']['Msg']
                            )
                        );
                    } else {
                        $this->getLogger()->info(
                            sprintf(
                                'API did not return status for delivery request: %s',
                                $deliveryRequest->getId()
                            )
                        );
                    }
                }
            }
        } catch (DeliveryServiceException $e) {
            $this->getLogger()->crit(
                sprintf('Internal error: %s', $e->getMessage())
            );

            $this->getLogger()->debug($e->getTraceAsString());
        }


        return $deliveryRequest;
    }

    public function parseJsonTracking(array $json): array
    {
        return [];
    }
}
