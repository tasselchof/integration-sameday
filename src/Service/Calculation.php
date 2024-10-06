<?php
/**
 * Created by PhpStorm.
 * User: mtodo
 * Date: 31.07.19
 * Time: 15:30
 */

namespace Octava\Integrations\Sameday\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Octava\Integrations\Sameday\SamedayClasses\AwbRecipientEntityObject;
use Octava\Integrations\Sameday\SamedayClasses\SamedayPostAwbEstimationRequest;
use Orderadmin\Application\Model\Manager\ObjectManagerAwareInterface;
use Orderadmin\Application\Service\Boxing;
use Orderadmin\Application\Traits\ObjectManagerAwareTrait;
use Orderadmin\DeliveryServices\Entity\DeliveryRequest;
use Orderadmin\DeliveryServices\Entity\Rate;
use Orderadmin\DeliveryServices\Entity\ServicePoint;
use Orderadmin\DeliveryServices\Exception\DeliveryServiceException;
use Orderadmin\DeliveryServices\Model\Feature\V2\CalculationProviderInterface;
use Orderadmin\DeliveryServices\Traits\DeliveryServiceV2Trait;
use Sameday\Exceptions\SamedayAuthenticationException;
use Sameday\Exceptions\SamedayAuthorizationException;
use Sameday\Exceptions\SamedayBadRequestException;
use Sameday\Exceptions\SamedayNotFoundException;
use Sameday\Exceptions\SamedayOtherException;
use Sameday\Exceptions\SamedaySDKException;
use Sameday\Exceptions\SamedayServerException;
use Sameday\Objects\ParcelDimensionsObject;
use Sameday\Objects\PostAwb\Request\ThirdPartyPickupEntityObject;
use Sameday\Objects\Types\PackageType;
use Sameday\Responses\SamedayPostAwbEstimationResponse;
use Sameday\Sameday;

class Calculation extends Integration implements CalculationProviderInterface
{
    use DeliveryServiceV2Trait;

    /**
     * @throws SamedayOtherException
     * @throws SamedaySDKException
     * @throws SamedayBadRequestException
     * @throws SamedayServerException
     * @throws SamedayAuthenticationException
     * @throws DeliveryServiceException
     * @throws SamedayAuthorizationException
     * @throws SamedayNotFoundException
     */
    public function calculateShipment(
        DeliveryRequest $deliveryRequest,
        float $weight,
        float $x = 0,
        float $y = 0,
        float $z = 0,
        array $data = []
    ): array {
        $settings = $this->getAppConfig();
        $integration = $this->getIntegration();

        if (empty($settings['username']) || empty($settings['password'])) {
            throw new DeliveryServiceException($this->getTranslator()->translate(
                sprintf('Integration ID %s username or password not set', $integration->getId())
            ));
        }

        if (empty($settings['servicePoint'])) {
            throw new DeliveryServiceException($this->getTranslator()->translate(
                sprintf('Integration ID %s Pickup point not set', $integration->getId())
            ));
        }

        $x /= 10;
        $y /= 10;
        $z /= 10;
        $weight /= 100;

        $recipient = $this->getRecipientDetails($deliveryRequest);
        $sender = $this->getSenderDetails($deliveryRequest);

        if (empty($recipient['postalCode'])) {
            throw new DeliveryServiceException($this->getTranslator()->translate('Recipient postcode not found'));
        }

        $parcel = [new ParcelDimensionsObject($weight, $x, $y, $z)];

        $rates = $this->getAvailableRates();

        $samedayClient = new SamedayClient($settings['username'], $settings['password']);
        $sameday = new Sameday($samedayClient);

        $request = new SamedayPostAwbEstimationRequest(
            $settings['servicePoint'],
            new PackageType(PackageType::PARCEL),
            $parcel,
            new AwbRecipientEntityObject(
                $recipient['cityString'],
                $recipient['countyString'],
                null, null, null, null, null,
                $recipient['postalCode']
            ),
            0
        );

        $services = $this->getServiceEstimations($sameday, $request, $rates, $sender);

        return $this->processRates($rates, $services);
    }

    private function getRecipientDetails(DeliveryRequest $deliveryRequest): array {
        return [
            'postalCode' => $deliveryRequest->getRecipientLocality()?->getPostcode(),
            'cityString' => $deliveryRequest->getRecipientLocality()?->getName(),
            'countyString' => $deliveryRequest->getRecipientLocality()?->getArea()?->getName()
        ];
    }

    private function getSenderDetails(DeliveryRequest $deliveryRequest): array {
        return [
            'postalCode' => $deliveryRequest->getSenderAddress()?->getPostcode(),
            'cityString' => $deliveryRequest->getSenderAddress()?->getLocality()?->getName(),
            'countyString' => $deliveryRequest->getSenderAddress()?->getLocality()?->getArea()?->getName()
        ];
    }

    private function getAvailableRates(): array {
        $rates = $this->getObjectManager()->getRepository(Rate::class)->findBy([
            'deliveryService' => $this->getDeliveryService(),
            'state' => Rate::STATE_ACTIVE,
        ]);

        return $rates;
    }

    private function getServiceEstimations(Sameday $sameday, SamedayPostAwbEstimationRequest $request, array $rates, array $sender): array {
        $services = [];

        foreach ($rates as $rate) {
            $serviceId = $rate->getExtId();
            $request->setServiceId($serviceId);
            $request->setThirdPartyPickup(new ThirdPartyPickupEntityObject(
                $sender['cityString'],
                $sender['countyString'],
                null, null, null, null,
                $sender['postalCode']
            ));

            try {
                $res = $sameday->postAwbEstimation($request);
                if ($res instanceof SamedayPostAwbEstimationResponse) {
                    $services[$serviceId] = json_decode($res->getRawResponse()->getBody(), true);
                }
            } catch (SamedayBadRequestException $e) {
                $services[$serviceId] = $this->handleErrors($e);
            }
        }

        return $services;
    }

    private function handleErrors(SamedayBadRequestException $e): array {
        $errors = [];
        if (!empty($e->getErrors())) {
            foreach ($e->getErrors() as $error) {
                $errors[] = json_encode($error['errors']);
            }
        } elseif (!empty($e->getRawResponse()->getBody())) {
            $body = json_decode($e->getRawResponse()->getBody(), true);
            $errors = $body['errors'] ?? [];
        }

        foreach ($errors as &$error) {
            if ($error == '["Invalid service."]') {
                $error = 'This rate is unavailable';
            }
        }

        return ['error' => $errors];
    }

    private function processRates(array $rates, array $services): array {
        $return = [];
        foreach ($rates as $rate) {
            $service = $services[$rate->getExtId()] ?? null;
            if (!empty($service)) {
                $return[] = $this->parseApiResult($rate, $service);
            }
        }

        return $return;
    }

    public function parseApiResult(
        Rate         $rate,
        array        $service,
        ServicePoint $servicePoint = null
    ): array {
        $integration = $this->getIntegration();

        $result = [
            'id' => $rate->getId(),
            'name' => $rate->getName(),
            'integration' => [
                'id' => $integration->getId(),
                'name' => $integration->getName(),
            ],
            'description' => $rate->getComment(),
            'type' => $rate->getType(),
            'currency' => [
                'code' => $rate->getCurrency()->getCode(),
                'symbol' => $rate->getCurrency()->getSymbol(),
            ],
            'deliveryTime' => [
                'min' => null,
            ],
            'deliveryService' => [
                'id' => $this->getDeliveryService()->getId(),
                'name' => $this->getDeliveryService()->getName(),
            ],
        ];

        if (! empty($service['error'])) {
            $result['errors'] = $service['error'];
        } else {
            $result = array_merge($result, [
                'deliveryPrice' => $service['amount'],
                'deliveryTime' => [
                    'max' => new \DateTime(sprintf('today +%s hours', $service['time'])),
                ],
                'raw' => base64_encode(json_encode($service)),
            ]);
        }

        if (! is_null($servicePoint)) {
            $result['service-point'] = [
                'id' => $servicePoint->getId(),
                'extId' => $servicePoint->getExtId(),
                'name' => $servicePoint->getName(),
                'geo' => ! empty($servicePoint->getGeo()) ? join(',', $servicePoint->getGeoArray()) : null,
                'address' => $servicePoint->getRawAddress(),
                'phone' => $servicePoint->getRawPhone(),
                'timetable' => $servicePoint->getRawTimetable(),
                'description' => $servicePoint->getRawDescription(),
            ];
        }

        return $result;
    }
}
