<?php

declare(strict_types=1);

namespace Octava\Integration\Sameday\Service;

use Octava\Integration\Sameday\SamedayClasses\AwbRecipientEntityObject;
use Octava\Integration\Sameday\SamedayClasses\SamedayPostAwbRequest;
use Octava\Integration\Sameday\Service\Integration;
use Orderadmin\DeliveryServices\Entity\DeliveryRequest;
use Orderadmin\DeliveryServices\Entity\Processing\Task;
use Orderadmin\DeliveryServices\Exception\DeliveryRequestException;
use Orderadmin\DeliveryServices\Exception\DeliveryServiceException;
use Orderadmin\DeliveryServices\Model\Feature\ShipmentProviderInterface;
use Orderadmin\Integrations\Entity\AbstractSource;
use Sameday\Exceptions\SamedayAuthenticationException;
use Sameday\Exceptions\SamedayAuthorizationException;
use Sameday\Exceptions\SamedayBadRequestException;
use Sameday\Exceptions\SamedayNotFoundException;
use Sameday\Exceptions\SamedayOtherException;
use Sameday\Exceptions\SamedaySDKException;
use Sameday\Exceptions\SamedayServerException;
use Sameday\Objects\ParcelDimensionsObject;
use Sameday\Objects\Types\AwbPaymentType;
use Sameday\Objects\Types\PackageType;
use Sameday\Sameday;

use function json_decode;
use function json_encode;
use function sprintf;

class Shipment extends Integration implements
    ShipmentProviderInterface
{
    protected ?Task $task             = null;
    protected ?AbstractSource $source = null;
    protected ?string $trackingNumber = null;

    public function calculateShipment(DeliveryRequest $deliveryRequest): array
    {
        return [];
    }

    /**
     * @throws SamedayOtherException
     * @throws SamedaySDKException
     * @throws SamedayBadRequestException
     * @throws SamedayServerException
     * @throws SamedayAuthenticationException
     * @throws DeliveryRequestException
     * @throws DeliveryServiceException
     * @throws SamedayAuthorizationException
     * @throws SamedayNotFoundException
     */
    public function createShipment(
        DeliveryRequest $deliveryRequest,
        array $calculation = [],
        ?Task $task = null
    ) {
        if (! empty($deliveryRequest->getTrackingNumber())) {
            throw new DeliveryServiceException(
                $this->getTranslator()->translate(
                    sprintf(
                        'Delivery request ID %s already has tracking number',
                        $deliveryRequest->getId()
                    )
                )
            );
        }
        $settings = $this->getDeliveryServiceManager()
            ->getIntegrationSettings(
                $deliveryRequest->getIntegration()
            );

        if (empty($settings['username']) || empty($settings['password'])) {
            throw new DeliveryServiceException(
                $this->getTranslator()->translate(
                    sprintf(
                        'Integration ID %s username or password not set',
                        $deliveryRequest->getIntegration()->getId()
                    )
                )
            );
        }

        if (empty($deliveryRequest->getRecipientName())) {
            throw new DeliveryRequestException(
                $this->getTranslator()->translate('Recipient name is not set')
            );
        }

        $places = $deliveryRequest->getPlaces();

        if (empty($places->count())) {
            throw new DeliveryServiceException(
                $this->getTranslator()->translate(
                    sprintf(
                        'Delivery request ID %s is empty',
                        $deliveryRequest->getId()
                    )
                )
            );
        }

        if (empty($deliveryRequest->getRecipientPhone()?->getPhone())) {
            throw new DeliveryServiceException(
                $this->getTranslator()->translate(
                    'Recipient phone is not set'
                )
            );
        }

        $email = null;
        if (! empty($deliveryRequest->getServicePoint())) {
            $servicePoint = $deliveryRequest->getServicePoint()->getExtId();
            if (! empty($deliveryRequest->getRecipient()->getEmail())) {
                $email = $deliveryRequest->getRecipient()->getEmail();
            } else {
                throw new DeliveryServiceException(
                    $this->getTranslator()->translate(
                        'Shipment with locker rate should have recipient email'
                    )
                );
            }
        }

        $parcels = [];
        foreach ($places as $place) {
            $parcels[] = new ParcelDimensionsObject(
                $place->getWeight() / 100,
                $place->getDimensions()['x'] / 10,
                $place->getDimensions()['y'] / 10,
                $place->getDimensions()['z'] / 10
            );
        }

        $recipient = $this->getRecipientDetails($deliveryRequest);

        $data = new SamedayPostAwbRequest(
            $settings['servicePoint'],
            new PackageType(PackageType::PARCEL),
            $parcels,
            $deliveryRequest->getRate()->getExtId(),
            new AwbPaymentType(AwbPaymentType::CLIENT),
            new AwbRecipientEntityObject(
                null,
                null,
                $recipient['address'],
                $recipient['name'],
                $recipient['phone'],
                $email,
                null,
                $recipient['postalCode'],
            ),
            0,
            $deliveryRequest->getPayment()
        );

        if (! empty($servicePoint)) {
            $data->setLockerLastMile($servicePoint);
        }

        $task->setResult($data);

        $errors = [];

        $samedayClient = new SamedayClient($settings['username'], $settings['password']);
        $sameday       = new Sameday($samedayClient);
        $res           = $sameday->postAwb($data);

        $res = json_decode($res->getRawResponse()->getBody(), true);

        if (! empty($res['awbNumber'])) {
            $trackingNumber = $res['awbNumber'];

            $this->getDeliveryRequestManager()->saveDeliveryRequest(
                [
                    'trackingNumber' => $trackingNumber,
                    'extId'          => $trackingNumber,
                ],
                $deliveryRequest
            );
        } else {
            $deliveryRequest->setState(DeliveryRequest::STATE_ERROR);
            $errors = $res['errors'] ?? [];

            $this->getLogger()->debug(json_encode($errors));
            $errors[$deliveryRequest->getId()]['message']
                = json_encode($errors);

            $this->getObjectManager()->flush();

            throw new DeliveryRequestException(
                json_encode($errors)
            );
        }

        $this->getObjectManager()->flush([$deliveryRequest, $task]);

        if (empty($errors) && ! empty($res)) {
            return [
                'state'        => Task::STATE_CLOSED,
                'exportResult' => $res,
            ];
        } else {
            return $errors;
        }
    }

    private function getRecipientDetails(DeliveryRequest $deliveryRequest): array
    {
        $address = $deliveryRequest->getRecipientAddress()->getNotFormal();
        if (empty($address)) {
            $address = $deliveryRequest->getRecipientLocality() . ' '
                . $deliveryRequest->getRecipientAddress()?->getStreet() . ' '
                . $deliveryRequest->getRecipientAddress()?->getHouse();
        }
        return [
            'address'      => $address,
            'name'         => $deliveryRequest->getRecipientName(),
            'phone'        => $deliveryRequest->getRecipientPhone()?->getPhone(),
            'postalCode'   => $deliveryRequest->getRecipientLocality()?->getPostcode(),
            'cityString'   => $deliveryRequest->getRecipientLocality()?->getName(),
            'countyString' => $deliveryRequest->getRecipientLocality()?->getArea()?->getName(),
        ];
    }
}
