<?php

declare(strict_types=1);

namespace Octava\Integration\Sameday\Service\V2;

use Octava\Integration\Sameday\Exception\SamedayException;
use Sameday\Requests\SamedayDeleteAwbRequest;
use Sameday\Requests\SamedayGetAwbStatusRequest;
use Sameday\Requests\SamedayPostAwbRequest;
use Sameday\SamedayClient as SamedaySdkClient;

class SamedayClient
{
    private SamedaySdkClient $client;
    private string $username;
    private string $password;
    private string $apiUrl;
    private bool $testMode;

    public function __construct(
        string $username,
        string $password,
        string $apiUrl = 'https://api.sameday.ro',
        bool $testMode = false
    ) {
        $this->username = $username;
        $this->password = $password;
        $this->apiUrl   = $apiUrl;
        $this->testMode = $testMode;

        $this->client = new SamedaySdkClient(
            $this->username,
            $this->password,
            $this->apiUrl,
            $this->testMode ? 'test' : 'live'
        );
    }

    public function createShipment(array $shipmentData): array
    {
        try {
            $request = new SamedayPostAwbRequest(
                $shipmentData['pickup_id'] ?? null,
                $shipmentData['package_type'] ?? 1,
                $shipmentData['package_number'] ?? 1,
                $shipmentData['package_weight'] ?? 1,
                $shipmentData['package_length'] ?? 1,
                $shipmentData['package_width'] ?? 1,
                $shipmentData['package_height'] ?? 1,
                $shipmentData['service_id'] ?? null,
                $shipmentData['recipient'] ?? null,
                $shipmentData['sender'] ?? null,
                $shipmentData['parcels'] ?? [],
                $shipmentData['payment'] ?? null,
                $shipmentData['observation'] ?? null,
                $shipmentData['package_content'] ?? null,
                $shipmentData['custom_delivery_date'] ?? null,
                $shipmentData['custom_delivery_time'] ?? null
            );

            $response = $this->client->postAwb($request);

            return [
                'tracking_number' => $response->getAwbNumber(),
                'awb_number'      => $response->getAwbNumber(),
                'cost'            => $response->getCost(),
                'estimated_cost'  => $response->getEstimatedCost(),
            ];
        } catch (\Exception $e) {
            throw new SamedayException(
                'Failed to create shipment: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    public function getShipmentStatus(string $trackingNumber): array
    {
        try {
            $request  = new SamedayGetAwbStatusRequest($trackingNumber);
            $response = $this->client->getAwbStatus($request);

            return [
                'status'       => $response->getStatus(),
                'status_label' => $response->getStatusLabel(),
                'delivered_at' => $response->getDeliveredAt(),
                'last_updated' => $response->getLastUpdated(),
            ];
        } catch (\Exception $e) {
            throw new SamedayException(
                'Failed to get shipment status: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    public function cancelShipment(string $trackingNumber): array
    {
        try {
            $request  = new SamedayDeleteAwbRequest($trackingNumber);
            $response = $this->client->deleteAwb($request);

            return [
                'success' => true,
                'message' => 'Shipment cancelled successfully',
            ];
        } catch (\Exception $e) {
            throw new SamedayException(
                'Failed to cancel shipment: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    public function getServices(): array
    {
        try {
            $services = $this->client->getServices();
            $result   = [];

            foreach ($services as $service) {
                $result[] = [
                    'id'             => $service->getId(),
                    'name'           => $service->getName(),
                    'type'           => $service->getType(),
                    'price'          => $service->getPrice(),
                    'price_currency' => $service->getPriceCurrency(),
                ];
            }

            return $result;
        } catch (\Exception $e) {
            throw new SamedayException(
                'Failed to get services: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    public function getServicePoints(): array
    {
        try {
            $servicePoints = $this->client->getServicePoints();
            $result        = [];

            foreach ($servicePoints as $servicePoint) {
                $result[] = [
                    'id'             => $servicePoint->getId(),
                    'name'           => $servicePoint->getName(),
                    'address'        => $servicePoint->getAddress(),
                    'city'           => $servicePoint->getCity(),
                    'county'         => $servicePoint->getCounty(),
                    'contact_person' => $servicePoint->getContactPerson(),
                    'phone'          => $servicePoint->getPhone(),
                    'email'          => $servicePoint->getEmail(),
                    'program'        => $servicePoint->getProgram(),
                ];
            }

            return $result;
        } catch (\Exception $e) {
            throw new SamedayException(
                'Failed to get service points: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
