<?php

declare(strict_types=1);

namespace Octava\Integration\Sameday\Service\V2;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Dot\DependencyInjection\Attribute\Inject;
use Octava\Integration\Sameday\Exception\SamedayException;
use Octava\Integration\Sameday\Transformer\ShipmentTransformer;
use Orderadmin\DeliveryServices\Entity\DeliveryRequest;
use Orderadmin\DeliveryServices\Entity\Processing\Task;
use Orderadmin\DeliveryServices\Exception\DeliveryRequestException;
use Orderadmin\DeliveryServices\Exception\DeliveryServiceException;
use Orderadmin\DeliveryServices\Model\Feature\V2\ShipmentProviderInterface;
use Orderadmin\DeliveryServices\Model\Feature\V2\TrackingNumberProviderInterface;
use Orderadmin\DeliveryServices\Traits\Feature\ConnectionAwareTrait;
use Psr\Log\LoggerInterface;
use Sameday\Requests\SamedayPostAwbRequest;
use Sameday\Exceptions\SamedayServerException;
use Sameday\Sameday;

use function json_decode;
use function json_encode;
use function sprintf;

class Shipment implements ShipmentProviderInterface, TrackingNumberProviderInterface
{
    use ConnectionAwareTrait;

    protected ?DeliveryRequest $deliveryRequest = null;
    protected ?Task $task = null;
    protected SamedayPostAwbRequest $postAwbRequest;
    protected string $trackingNumber;

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

    private function objectToArray(array|object $object): array
    {
        if (is_array($object)) {
            return array_map([$this, 'objectToArray'], $object);
        }
        
        if (is_object($object)) {
            $reflection = new \ReflectionClass($object);
            $properties = $reflection->getProperties(\ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE);
            
            $array = [];
            foreach ($properties as $property) {
                $property->setAccessible(true);
                $value = $property->getValue($object);
                $propertyName = $property->getName();
                
                // Convert property name from camelCase to snake_case for consistency
                $arrayKey = ltrim($propertyName, '*');
                
                if (is_object($value) || is_array($value)) {
                    $array[$arrayKey] = $this->objectToArray($value);
                } else {
                    $array[$arrayKey] = $value;
                }
            }
            
            return $array;
        }
        
        return $object;
    }

    public function prepareTask(Task $task): array
    {
        // Get delivery request from task
        $deliveryRequest = $task->getDeliveryRequest();
        $this->deliveryRequest = $deliveryRequest;
        $this->task = $task;

        // Set up the integration context
        $this->setCarrierIntegration($deliveryRequest->getIntegration());
        
        // Validate tracking number doesn't already exist
        if (! empty($deliveryRequest->getTrackingNumber())) {
            throw new DeliveryServiceException(
                sprintf(
                    'Delivery request ID %s already has tracking number',
                    $deliveryRequest->getId()
                )
            );
        }

        // Validate recipient name
        if (empty($deliveryRequest->getRecipientName())) {
            throw new DeliveryRequestException('Recipient name is not set');
        }

        // Validate places exist
        $places = $deliveryRequest->getPlaces();
        if (empty($places->count())) {
            throw new DeliveryServiceException(
                sprintf(
                    'Delivery request ID %s is empty',
                    $deliveryRequest->getId()
                )
            );
        }

        // Validate recipient phone
        if (empty($deliveryRequest->getRecipientPhone()?->getPhone())) {
            throw new DeliveryServiceException('Recipient phone is not set');
        }

        // Handle service point and email validation
        $email = null;
        $servicePoint = null;
        if (! empty($deliveryRequest->getServicePoint())) {
            $servicePoint = $deliveryRequest->getServicePoint()->getExtId();
            if (! empty($deliveryRequest->getRecipient()->getEmail())) {
                $email = $deliveryRequest->getRecipient()->getEmail();
            } else {
                throw new DeliveryServiceException(
                    'Shipment with locker rate should have recipient email'
                );
            }
        }

        // Transform delivery request to Sameday request using transformer
        $settings = $this->source->getSettings();
        $transformer = new ShipmentTransformer($settings);
        $postAwbRequest = $transformer->transformDeliveryRequest($deliveryRequest);

        $this->postAwbRequest = $postAwbRequest;
        
        return $this->objectToArray($postAwbRequest);
    }


    public function createShipment(): array
    {
        if (!$this->deliveryRequest || !$this->task) {
            throw new DeliveryServiceException('Delivery request or task not set. Call prepareTask() first.');
        }
        
        $protectedSettings = $this->source->getSettingsProtected();

        // Validate credentials
        if (empty($protectedSettings['auth']['username']) || empty($protectedSettings['auth']['password'])) {
            throw new DeliveryServiceException(
                sprintf(
                    'Integration ID %s username or password not set',
                    $this->deliveryRequest->getIntegration()->getId()
                )
            );
        }

        try {
            // Create Sameday client and make API request
            $samedayClient = new \Octava\Integration\Sameday\Service\SamedayClient(
                $protectedSettings['auth']['username'],
                $protectedSettings['auth']['password']
            );
            $sameday = new Sameday($samedayClient);
            $response = $sameday->postAwb($this->postAwbRequest);

            $responseData = json_decode($response->getRawResponse()->getBody(), true);

            // Handle successful response
            if (! empty($responseData['awbNumber'])) {
                $trackingNumber = $responseData['awbNumber'];
                $this->trackingNumber = $trackingNumber;

                $result = [
                    'state'        => Task::STATE_CLOSED,
                    'exportResult' => $responseData,
                ];

                return $result;
            } else {
                var_dump($responseData);die();
                $errors = $responseData['errors'] ?? [];

                $this->logger->debug('Sameday API error: ' . json_encode($errors));

                throw new SamedayException('Sameday API error: ', $this->deliveryRequest, $errors);
            }

        } catch (SamedayServerException $e) {
            $errorsStr = $e->getRawResponse()->getBody();
            $errors = json_decode($errorsStr, true);

            $this->logger->error('Sameday API error: ' . $e->getMessage());
            throw new SamedayException('Sameday API error: ' . $e->getMessage(), $this->deliveryRequest, $errors);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create Sameday shipment: ' . $e->getMessage());
            throw new SamedayException($e->getMessage());
        }
    }

    public function getTrackingNumber(): string
    {
        if (empty($this->trackingNumber)) {
            throw new SamedayException('Tracking number is not set');
        }

        return $this->trackingNumber;
    }
}
