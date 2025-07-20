<?php

declare(strict_types=1);

namespace Octava\Integration\Sameday\Transformer;

use Octava\Integration\Sameday\Exception\SamedayException;
use Orderadmin\DeliveryServices\Entity\DeliveryRequest;

use function implode;
use function max;
use function round;

class ShipmentTransformer
{
    public function transformDeliveryRequest(DeliveryRequest $deliveryRequest): array
    {
        try {
            $sender    = $this->transformSenderAddress($deliveryRequest);
            $recipient = $this->transformRecipientAddress($deliveryRequest);

            $packageWeight = 0;
            $packageLength = 0;
            $packageWidth  = 0;
            $packageHeight = 0;

            // Use places instead of products for package dimensions
            $places = $deliveryRequest->getPlaces();
            foreach ($places as $place) {
                $packageWeight += $place->getWeight();
                $packageLength  = max($packageLength, $place->getDimensions()['x'] ?? 0);
                $packageWidth   = max($packageWidth, $place->getDimensions()['y'] ?? 0);
                $packageHeight += $place->getDimensions()['z'] ?? 0;
            }

            return [
                'pickup_id'            => $deliveryRequest->getServicePoint()?->getExtId(),
                'package_type'         => 1, // Standard package
                'package_number'       => 1,
                'package_weight'       => max(1, round($packageWeight / 100, 2)), // Convert from grams to kg
                'package_length'       => max(1, round($packageLength / 10, 2)), // Convert from mm to cm
                'package_width'        => max(1, round($packageWidth / 10, 2)), // Convert from mm to cm
                'package_height'       => max(1, round($packageHeight / 10, 2)), // Convert from mm to cm
                'service_id'           => $deliveryRequest->getRate()?->getExtId(),
                'recipient'            => $recipient,
                'sender'               => $sender,
                'parcels'              => $this->transformPlaces($places),
                'payment'              => $this->transformPayment($deliveryRequest),
                'observation'          => $deliveryRequest->getRecipientComment(),
                'package_content'      => $this->getPackageContent($places),
                'custom_delivery_date' => $deliveryRequest->getDeliveryDate(),
                'custom_delivery_time' => $deliveryRequest->getDeliveryTimeStart() . '-' . $deliveryRequest->getDeliveryTimeEnd(),
            ];
        } catch (\Exception $e) {
            throw new SamedayException(
                'Failed to transform delivery request: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    private function transformSenderAddress(DeliveryRequest $deliveryRequest): array
    {
        $senderAddress = $deliveryRequest->getSenderAddress();
        return [
            'name'        => $deliveryRequest->getSender()?->getName(),
            'phone'       => $deliveryRequest->getSender()?->getName(), // Placeholder - use name as phone
            'email'       => 'sender@example.com', // Placeholder email
            'address'     => $senderAddress?->getStreet(),
            'city'        => $senderAddress?->getLocality()?->getName(),
            'county'      => $senderAddress?->getLocality()?->getArea()?->getName(),
            'postal_code' => $senderAddress?->getPostcode(),
            'country'     => $senderAddress?->getLocality()?->getArea()?->getCountry()?->getCode(),
        ];
    }

    private function transformRecipientAddress(DeliveryRequest $deliveryRequest): array
    {
        $recipientAddress = $deliveryRequest->getRecipientAddress();
        return [
            'name'        => $deliveryRequest->getRecipientName(),
            'phone'       => $deliveryRequest->getRecipientPhone()?->getPhone(),
            'email'       => $deliveryRequest->getRecipient()?->getName(), // Placeholder - use name as email
            'address'     => $recipientAddress?->getStreet(),
            'city'        => $deliveryRequest->getRecipientLocality()?->getName(),
            'county'      => $deliveryRequest->getRecipientLocality()?->getArea()?->getName(),
            'postal_code' => $deliveryRequest->getRecipientLocality()?->getPostcode(),
            'country'     => $deliveryRequest->getRecipientLocality()?->getArea()?->getCountry()?->getCode(),
        ];
    }

    private function transformPlaces($places): array
    {
        $parcels = [];

        foreach ($places as $place) {
            $parcels[] = [
                'name'     => $place->getName() ?? 'Package',
                'code'     => $place->getSku() ?? '',
                'quantity' => $place->getQuantity() ?? 1,
                'price'    => $place->getPrice() ?? 0,
                'weight'   => $place->getWeight(),
                'length'   => $place->getDimensions()['x'] ?? 0,
                'width'    => $place->getDimensions()['y'] ?? 0,
                'height'   => $place->getDimensions()['z'] ?? 0,
            ];
        }

        return $parcels;
    }

    private function transformPayment(DeliveryRequest $deliveryRequest): array
    {
        return [
            'type'     => $deliveryRequest->getPayment() ?? 'sender',
            'amount'   => $deliveryRequest->getPayment(),
            'currency' => 'RON', // Default currency for Sameday
        ];
    }

    private function getPackageContent($places): string
    {
        $content = [];

        foreach ($places as $place) {
            $quantity  = $place->getQuantity() ?? 1;
            $name      = $place->getName() ?? 'Package';
            $content[] = $quantity . 'x ' . $name;
        }

        return implode(', ', $content);
    }
}
