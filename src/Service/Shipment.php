<?php
/**
 * Created by PhpStorm.
 * User: mtodo
 * Date: 29.10.19
 * Time: 12:03
 */

namespace Octava\Integrations\Sameday\Service\DeliveryServices;

use Orderadmin\Application\Model\LoggerAwareInterface;
use Orderadmin\Application\Model\Manager\ObjectManagerAwareInterface;
use Orderadmin\Application\Model\Manager\OrderadminManagerAwareInterface;
use Orderadmin\Application\Model\Manager\ServiceManagerAwareInterface;
use Orderadmin\Application\Traits\LoggerAwareTrait;
use Orderadmin\Application\Traits\ObjectManagerAwareTrait;
use Orderadmin\Application\Traits\OrderadminManagerAwareTrait;
use Orderadmin\Application\Traits\ServiceManagerAwareTrait;
use Orderadmin\Clients\Entity\Address;
use Orderadmin\DeliveryServices\Entity\DeliveryRequest;
use Orderadmin\DeliveryServices\Entity\Processing\Task;
use Orderadmin\DeliveryServices\Entity\Sender;
use Orderadmin\DeliveryServices\Exception\DeliveryRequestException;
use Orderadmin\DeliveryServices\Model\DeliveryRequestManagerAwareInterface;
use Orderadmin\DeliveryServices\Model\DeliveryServiceManagerInterface;
use Orderadmin\DeliveryServices\Model\Feature\V2\ShipmentProviderInterface;
use Orderadmin\DeliveryServices\Model\Feature\V2\TrackingNumberProviderInterface;
use Orderadmin\DeliveryServices\Traits\DeliveryRequestManagerAwareTrait;
use Orderadmin\DeliveryServices\Traits\DeliveryServicesServiceTrait;
use Orderadmin\DeliveryServices\Traits\DeliveryServiceV2Trait;
use Orderadmin\Integrations\Entity\AbstractSource;
use Octava\Integrations\Sameday\Exception\SamedayException;
use Octava\Integrations\Sameday\Service\Convert;
use Octava\Integrations\Sameday\Traits\ConverterTrait;
use Octava\Integrations\Sameday\Traits\DeliveryServicesTrait;
use Orderadmin\Locations\Entity\Area;

class Shipment implements
    ShipmentProviderInterface,
    ObjectManagerAwareInterface,
    DeliveryServiceManagerInterface,
    ServiceManagerAwareInterface,
    OrderadminManagerAwareInterface,
    TrackingNumberProviderInterface,
    DeliveryRequestManagerAwareInterface,
    LoggerAwareInterface
{
    use DeliveryServiceV2Trait,
        ObjectManagerAwareTrait,
        ServiceManagerAwareTrait,
        OrderadminManagerAwareTrait,
        DeliveryServicesTrait,
        DeliveryServicesServiceTrait,
        DeliveryRequestManagerAwareTrait,
        LoggerAwareTrait,
        ConverterTrait;

    protected ? Task $task = null;
    protected ?AbstractSource $source = null;
    protected ?string $trackingNumber = null;

    public function getTrackingNumber():string
    {
        return $this->trackingNumber;
    }

    public function prepareTask(Task $task) : array
    {
        $this->task = $task;

        $deliveryRequest = $task->getDeliveryRequest();
        if (empty($deliveryRequest)) {
            throw new DeliveryRequestException(
                sprintf(
                    $this->getTranslator()->translate(
                        'Task ID[%s] has no delivery request'
                    ),
                    $task->getId()
                )
            );
        }

        return $this->createRequestData($deliveryRequest);
    }

    public function createShipment(): array
    {
        $deliveryRequest = $this->task->getDeliveryRequest();

        $isTransactionActive = $this->getObjectManager()->getConnection()
            ->isTransactionActive();

        if (empty($isTransactionActive)) {
            $this->getObjectManager()->beginTransaction();
        }

        if (! empty($deliveryRequest->getTrackingNumber())) {
            throw new SamedayException('The delivery request already has tracking number', 409);
        }

        try {
            $deliveryService = $this->getDeliveryRequestsService();
            $exportResult = $deliveryService->requestShipmentLabel($deliveryRequest, $this->task);

            if (! empty($exportResult[0]['trackingNumber'])) {
                $this->trackingNumber = $exportResult[0]['trackingNumber'];
            }

            if (empty($isTransactionActive)) {
                $this->getObjectManager()->flush();
                $this->getObjectManager()->commit();
            }
        } catch (DeliveryRequestException $e) {
            if (empty($isTransactionActive)) {
                $this->getObjectManager()->rollback();
            }

            $this->getDeliveryRequestManager()->saveDeliveryRequest(
                ['state' => DeliveryRequest::STATE_ERROR],
                $deliveryRequest
            );

            throw $e;
        }

        if (! $isTransactionActive) {
            $this->getOrderadminEventManager()->triggerDelayedEvents();
        }

        return [
            'state' => Task::STATE_CLOSED,
            'exportResult' => $exportResult,
        ];
    }

    public function getInternationalOptions(DeliveryRequest $deliveryRequest, DeliveryRequest\Place $place) : array
    {
        foreach ($place->getItems() as $item) {
            $description = $item->getEavAttribute('descriptions-for-customs') ??
                $item->getOrderProduct()->getProductOffer()->getEavAttribute('descriptions-for-customs');
            if (empty($description)) {
                $description = substr($item->getName(), 0, 50);
            }

            $harmonizedTariffCode = $item->getEavAttribute('harmonized-tariff-code') ??
                $item->getOrderProduct()->getProductOffer()->getEavAttribute('harmonized-tariff-code');

            if (empty($item->getEstimatedCost())) {
                throw new SamedayException(sprintf('Estimated cost for order product %s not set', $item->getOrderProduct()->getId()));
            }

            $customsItems[] = [
                'description' => $description,
                'harmonizedTariffCode' => $harmonizedTariffCode,
                'quantity' => $item->getCount(),
                'value' => $item->getEstimatedCost(),
                'countryOfOrigin' => $deliveryRequest->getSenderAddress()->getLocality()
                ->getCountry()->getCode()
            ];
        }

        if (! empty($customsItems)) {
            return [
                'contents' => 'merchandise',
                'customsItems' => $customsItems,
            ];
        } else {
            return [];
        }
    }

    private function getConverterFromSource(DeliveryRequest $deliveryRequest): Convert
    {
        $sourceId = $deliveryRequest->getIntegration()->getSetting('source');
        if (empty($sourceId)) {
            throw new SamedayException('Delivery service integration source is missing');
        }

        $source = $this->getObjectManager()->getRepository(
            AbstractSource::class
        )->findOneById($sourceId);
        if (empty($source)) {
            throw new SamedayException(sprintf('Source %s not found', $sourceId['source']));
        }

        return $this->getConverter($source);
    }

    private function getDimensionsAndWeight(DeliveryRequest\Place $place, DeliveryRequest $deliveryRequest, Sender $sender): array
    {
        $weightUnit = $sender->getWeightUnit() ?: $sender->getParent()->getWeightUnit();
        if (empty($weightUnit)) {
            throw new SamedayException(sprintf('Weight unit is missing for sender %s or for its parent sender', $sender->getId()));
        }
        $dimensionUnit = $sender->getDimensionUnit() ?: $sender->getParent()->getDimensionUnit();
        if (empty($dimensionUnit)) {
            throw new SamedayException(sprintf('Dimension unit is missing for sender %s or for its parent sender', $sender->getId()));
        }

        return [
            'weight' => $data['weight'] ?? $place->getWeight() ?? $deliveryRequest->getSender()->getWeight() ?? 0,
            'x' => $data['dimensions']['x'] ?? $place->getDimensions()['x'] ?? $deliveryRequest->getSender()->getDimensions()['x'] ?? 0,
            'y' => $data['dimensions']['y'] ?? $place->getDimensions()['y'] ?? $deliveryRequest->getSender()->getDimensions()['y'] ?? 0,
            'z' => $data['dimensions']['z'] ?? $place->getDimensions()['z'] ?? $deliveryRequest->getSender()->getDimensions()['z'] ?? 0,
            'weightUnit' => $weightUnit->getTitle(),
            'dimensionUnit' => $dimensionUnit->getTitle(),
        ];
    }

    private function getSenderRequestData(Sender $sender, DeliveryRequest $deliveryRequest): array
    {
        $senderProfile = $deliveryRequest->getSenderProfile();
        if (empty($senderProfile)) {
            throw new SamedayException('Sender profile is empty');
        }
        if (empty($senderProfile->getName())) {
            throw new SamedayException('Sender profile name is empty');
        }
        $senderAddress = $deliveryRequest->getSenderAddress();
        if (empty($senderAddress)) {
            throw new SamedayException('Sender address is empty');
        }
        $senderLocality = $senderAddress->getLocality();
        if (empty($senderLocality)) {
            throw new SamedayException('Sender address locality is empty');
        }
        $senderArea = $this->getObjectManager()->getRepository(
            Area::class
        )->findParentArea($senderLocality->getArea());
        if (empty($senderArea)) {
            throw new SamedayException('Sender address locality area not found');
        }

        if (empty($senderArea->getExtId()) || strlen($senderArea->getExtId()) != 2) {
            throw new SamedayException(sprintf('State abbreviation for area - %s is missing', $senderArea->getId()));
        }
        $house = $senderAddress->getHouse();
        $street = $senderAddress->getStreet();

        $address = (! empty($house)) ? $house . ' ' . $street : $street;

        $senderPhone = $deliveryRequest->getSenderPhone()?->getPhone()
            ?? $senderProfile->getPhones()['elements']?->first()?->getPhone()
            ?? ($sender->getDefaultSenderProfile()?->getPhones()?->first()?->getPhone() ?? null);

        return [
            'name'          => $senderProfile->getName(),
            'company'       => $senderProfile->getSurname() ?? $senderProfile->getName(),
            'street1'       => $address,
            'street2'       => null,
            'street3'       => null,
            'city'          => $senderLocality->getName(),
            'state'         => $senderArea->getExtId(),
            'postalCode'    => $senderAddress->getPostcode(),
            'country'       => $senderLocality->getCountry()->getCode(),
            'phone'         => $senderPhone,
            'residential'   => false,
        ];
    }

    private function getRecipientRequestData(DeliveryRequest $deliveryRequest): array
    {
        $recipientAddress = $deliveryRequest->getRecipientAddress();
        if (empty($recipientAddress)) {
            throw new SamedayException('Recipient address locality is empty');
        }
        $recipientLocality = $recipientAddress->getLocality();
        if (empty($recipientLocality)) {
            throw new SamedayException('Recipient address locality is empty');
        }
        $recipientArea = $this->getObjectManager()->getRepository(
            Area::class
        )->findParentArea($recipientLocality->getArea());
        if (empty($recipientArea)) {
            throw new SamedayException('Recipient address locality area is empty');
        }

        if (empty($recipientArea->getExtId()) || strlen($recipientArea->getExtId()) != 2) {
            throw new SamedayException(
                sprintf('State abbreviation for area - %s is missing', $recipientArea->getId())
            );
        }

        if (empty($deliveryRequest->getRecipientName())) {
            throw new SamedayException('Recipient name is missing');
        }

        $phone = $deliveryRequest->getRecipientPhone()?->getPhone();

        $address = $recipientAddress->getStreet();
        if (! empty($recipientAddress->getHouse())) {
            $address = $recipientAddress->getHouse() . ' ' . $address;
        }

        return [
            'name'          => $deliveryRequest->getRecipientName(),
            'street1'       => $address,
            'street2'       => null,
            'street3'       => null,
            'city'          => $recipientLocality->getName(),
            'state'         => $recipientArea->getExtId(),
            'postalCode'    => $recipientAddress->getPostcode(),
            'country'       => $recipientLocality->getCountry()->getCode(),
            'phone'         => $phone,
            'residential'   => false,
        ];
    }

    private function getRequestDataForAllPlaces(array $data): array
    {
        /** @var DeliveryRequest $deliveryRequest */
        $deliveryRequest = $data['deliveryRequest'];
        $places = $data['places'];
        $requestDataForAll = [];
        $confirmation = $deliveryRequest->getEavAttribute('integrations-sameday-shipment-confirmation', 'none');

        foreach ($places as $place) {
            // get dimensions and weight data
            $dimensionsAndWeight = $this->getDimensionsAndWeight($place, $deliveryRequest, $data['sender']);

            // main request data
            $requestData = [
                'placeId'               => $place->getId(),
                'carrierCode'           => $data['carrier'],
                'serviceCode'           => $data['serviceCode'],
                'packageCode'           => 'package',
                'confirmation'          => $confirmation,
                'shipDate'              => $data['shipDate']->format(
                    'Y-m-d'
                ),
                'weight'                => [
                    'value' => $dimensionsAndWeight['weight'],
                    'units' => $dimensionsAndWeight['weightUnit']
                ],
                'dimensions'            => [
                    'units'  => $dimensionsAndWeight['dimensionUnit'],
                    'length' => $dimensionsAndWeight['x'],
                    'width'  => $dimensionsAndWeight['y'],
                    'height' => $dimensionsAndWeight['z'],
                ],
                'shipFrom' => $this->getSenderRequestData($data['sender'], $deliveryRequest),
                'shipTo' => $this->getRecipientRequestData($deliveryRequest),
                'insuranceOptions'      => null,
                'internationalOptions'  => null,
                'advancedOptions'       => null,
            ];

            if ($requestData['shipTo']['country'] != $requestData['shipFrom']['country']) {
                $internationalOptions = $this->getInternationalOptions($deliveryRequest, $place);

                if (! empty($internationalOptions)) {
                    $requestData['internationalOptions'] = $internationalOptions;
                }
            }

            $requestDataForAll[] = $requestData;
        }

        return $requestDataForAll;
    }

    public function createRequestData(DeliveryRequest $deliveryRequest) : array
    {
        $converter = $this->getConverterFromSource($deliveryRequest);

        if (empty($deliveryRequest->getRate()) || $deliveryRequest->getRate()->getDeliveryService()->getExtId() == 'sameday'
        ) {
            throw new SamedayException(
                'Rate is empty or Sameday default rate is set'
            );
        }

        // get carrier
        $carrier = $converter->convertDeliveryServiceExtId(
            $deliveryRequest->getRate()->getDeliveryService()->getExtId()
        );

        // get places
        if (! empty($deliveryRequest->getPlaces())) {
            $places = $deliveryRequest->getPlaces();
        } else {
            throw new SamedayException('Places missing');
        }

        // get sender
        /** @var Sender $sender */
        $sender = $deliveryRequest->getSender();
        if (empty($sender)) {
            throw new SamedayException('Sender is missing');
        }
        if (! empty($sender->getParent())) {
            $sender = $sender->getParent();
        }

        // get shipping date
        $today = new \DateTime();
        $shipDate = (! empty($deliveryRequest->getSendDate())) ?
            $deliveryRequest->getSendDate() : $today;
        if ($shipDate <= $today) {
            $shipDate = $today;
        }

        // get service code
        $serviceCode = $converter
            ->convertDeliveryServiceRateExtId(
                $deliveryRequest->getRate()->getDeliveryService(),
                $deliveryRequest->getRate()->getExtId(),
                true
            );

        $data = [
            'deliveryRequest'   => $deliveryRequest,
            'carrier'           => $carrier,
            'shipDate'          => $shipDate,
            'serviceCode'       => $serviceCode,
            'places'            => $places,
            'sender'            => $sender
        ];

        return $this->getRequestDataForAllPlaces($data);
    }

    public function checkDeliveryRequest(DeliveryRequest $deliveryRequest): array
    {
        $errors = [];

        $converter = $this->getConverter($deliveryRequest->getSource());
        try {
            $carrier = $converter->convertDeliveryServiceExtId(
                $deliveryRequest->getRate()->getDeliveryService()->getExtId()
            );
        } catch (SamedayException $e) {
            if (! empty($data['debug'])) {
                throw $e;
            } else {
                $this->getLogger()->warn(
                    sprintf(
                        'Converting of the delivery service failed: %s',
                        $e->getMessage()
                    )
                );
            }
        }

        if (empty($carrier)) {
            $errors[] = $this->getTranslator()->translate(
                sprintf(
                    'Delivery service extId[%s] did not match any carrier code in Sameday',
                    $deliveryRequest->getRate()->getDeliveryService()->getExtId()
                )
            );
        }

        if (empty($deliveryRequest->getSendDate())) {
            $errors[] = $this->getTranslator()->translate(
                'Send date should be set for delivery request'
            );
        }

        if (empty($deliveryRequest->getOrder())) {
            $recipientAddress = $deliveryRequest->getRecipientAddress();
            if (empty($recipientAddress->getLocality())) {
                $errors[] = 'Recipient locality not set';
            }

            if (empty($recipientAddress->getPostcode())) {
                $errors[] = 'Recipient postcode in address not set';
            }

            if (empty($recipientAddress->getStreet())) {
                $errors[] = 'Recipient street in address not set';
            }

            $senderAddress = $deliveryRequest->getSenderAddress();
            if (empty($senderAddress)) {
                $senderAddress = $this->getObjectManager()->getRepository(
                    Address::class
                )->find($deliveryRequest->getSender()->getEavAttribute('sender-default-address'));
            }

            if (empty($senderAddress)) {
                $errors[] = 'Sender address not set';
            }

            if (empty($senderAddress->getLocality())) {
                $errors[] = 'Sender address locality not set';
            }

            if (empty($deliveryRequest->getWeight())) {
                $errors[] = 'Delivery request weight must be set!';
            }
        }
        return $errors;
    }
}
