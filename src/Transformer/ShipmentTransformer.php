<?php

declare(strict_types=1);

namespace Octava\Integration\Sameday\Transformer;

use Octava\Integration\Sameday\Exception\SamedayException;
use Octava\Integration\Sameday\SamedayClasses\AwbRecipientEntityObject;
use Octava\Integration\Sameday\SamedayClasses\SamedayPostAwbRequest;
use Orderadmin\DeliveryServices\Entity\DeliveryRequest;
use Sameday\Objects\ParcelDimensionsObject;
use Sameday\Objects\Types\AwbPaymentType;
use Sameday\Objects\Types\PackageType;

class ShipmentTransformer
{
    public function __construct(
        private array $settings
    ) {
    }

    public function transformDeliveryRequest(DeliveryRequest $deliveryRequest): object|array
    {
        try {
            // Create parcels from places
            $parcels = [];
            foreach ($deliveryRequest->getPlaces() as $place) {
                $parcels[] = new ParcelDimensionsObject(
                    $place->getWeight() / 100,
                    $place->getDimensions()['x'] / 10,
                    $place->getDimensions()['y'] / 10,
                    $place->getDimensions()['z'] / 10
                );
            }

            // Get recipient details
            $recipient = $this->getRecipientDetails($deliveryRequest);

            // Handle service point and email
            $email        = null;
            $servicePoint = null;
            if (! empty($deliveryRequest->getServicePoint())) {
                $servicePoint = $deliveryRequest->getServicePoint()->getExtId();
                if (! empty($deliveryRequest->getRecipient()->getEmail())) {
                    $email = $deliveryRequest->getRecipient()->getEmail();
                }
            }

            // Create Sameday request using constructor parameters
            $data = new SamedayPostAwbRequest(
                $this->settings['general']['servicePoint'],
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
                $deliveryRequest->getPayment() ?? 0
            );

            // Set service point if available
            if (! empty($servicePoint)) {
                $data->setLockerLastMile($servicePoint);
            }

            return $data;
        } catch (\Exception $e) {
            throw new SamedayException('Failed to transform delivery request: ' . $e->getMessage());
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
