<?php

declare(strict_types=1);

namespace Octava\Integration\Sameday\SamedayClasses;

use Sameday\Http\RequestBodyUrlEncoded;
use Sameday\Http\SamedayRequest;
use Sameday\Objects\ParcelDimensionsObject;
use Sameday\Objects\PostAwb\Request\ThirdPartyPickupEntityObject;
use Sameday\Objects\Types\AwbPaymentType;
use Sameday\Objects\Types\PackageType;
use Sameday\Requests\SamedayRequestInterface;

use function array_map;
use function array_merge;
use function count;

/**
 * Request to estimate cost for an AWB.
 */
class SamedayPostAwbEstimationRequest extends \Sameday\Requests\SamedayPostAwbEstimationRequest implements SamedayRequestInterface
{
    /** @var int */
    protected $pickupPointId;

    /** @var int|null */
    protected $contactPersonId;

    /** @var PackageType */
    protected $packageType;

    /** @var ParcelDimensionsObject[] */
    protected $parcelsDimensions;

    /** @var int */
    protected $serviceId;

    /** @var AwbPaymentType */
    protected $awbPayment;

    /** @var AwbRecipientEntityObject */
    protected $awbRecipient;

    /** @var float */
    protected $insuredValue;

    /**
     * Cash on delivery (can be 0)
     *
     * @var float
     */
    protected $cashOnDeliveryAmount;

    /** @var ThirdPartyPickupEntityObject|null */
    protected $thirdPartyPickup;

    /** @var int[] */
    protected $serviceTaxIds;

    /** @var string|null */
    protected $currency;

    /**
     * @param $pickupPointId
     * @param $contactPersonId
     * @param array $parcelsDimensions
     * @param $serviceId
     * @param AwbPaymentType $awbPayment
     * @param $insuredValue
     * @param $cashOnDeliveryAmount
     * @param array $serviceTaxIds
     * @param $currency
     */
    public function __construct(
        $pickupPointId,
        PackageType $packageType,
        array $parcelsDimensions,
        //        AwbPaymentType $awbPayment,
        AwbRecipientEntityObject $awbRecipient,
        $insuredValue,
        $serviceId = null,
        $cashOnDeliveryAmount = .0,
        ?ThirdPartyPickupEntityObject $thirdPartyPickup = null,
        array $serviceTaxIds = [],
        $currency = null
    ) {
        $this->pickupPointId     = $pickupPointId;
        $this->packageType       = $packageType;
        $this->parcelsDimensions = $parcelsDimensions;
        $this->serviceId         = $serviceId;
//        $this->awbPayment = $awbPayment;
        $this->awbRecipient         = $awbRecipient;
        $this->insuredValue         = $insuredValue;
        $this->cashOnDeliveryAmount = $cashOnDeliveryAmount;
        $this->thirdPartyPickup     = $thirdPartyPickup;
        $this->serviceTaxIds        = $serviceTaxIds;
        $this->currency             = $currency;
    }

    /**
     * @inheritDoc
     */
    public function buildRequest()
    {
        // Calculate weight for all parcels.
        $weight = 0;
        array_map(
            function (ParcelDimensionsObject $parcelDimensions) use (&$weight) {
                $weight += $parcelDimensions->getWeight();
            },
            $this->parcelsDimensions
        );

        $body = [
            'pickupPoint'   => $this->pickupPointId,
            'packageType'   => $this->packageType->getType(),
            'packageNumber' => count($this->parcelsDimensions),
            'packageWeight' => $weight,
            'service'       => $this->serviceId,
//            'awbPayment' => $this->awbPayment->getType(),
            'cashOnDelivery'   => $this->cashOnDeliveryAmount,
            'insuredValue'     => $this->insuredValue,
            'thirdPartyPickup' => $this->thirdPartyPickup ? 1 : 0,
        ];

        // Third party pickup.
        if ($this->thirdPartyPickup !== null) {
            $body = array_merge($body, ['thirdParty' => $this->thirdPartyPickup->getFields()]);
        }

        $body = array_merge($body, [
            'serviceTaxes' => $this->serviceTaxIds,
            'awbRecipient' => $this->awbRecipient->getFields(),
            'parcels'      => array_map(
                // Build parcel fields from ParcelDimensionsObject.
                function (ParcelDimensionsObject $parcelDimensions) {
                    return [
                        'weight' => $parcelDimensions->getWeight(),
                        'width'  => $parcelDimensions->getWidth(),
                        'length' => $parcelDimensions->getLength(),
                        'height' => $parcelDimensions->getHeight(),
                    ];
                },
                $this->parcelsDimensions
            ),
        ]);

        if ($this->currency !== null) {
            $body = array_merge($body, ['currency' => $this->currency]);
        }

        return new SamedayRequest(
            true,
            'POST',
            '/api/awb/estimate-cost',
            [],
            new RequestBodyUrlEncoded($body)
        );
    }

    /**
     * @return int
     */
    public function getPickupPointId()
    {
        return $this->pickupPointId;
    }

    /**
     * @param int $pickupPointId
     * @return $this
     */
    public function setPickupPointId($pickupPointId)
    {
        $this->pickupPointId = $pickupPointId;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getContactPersonId()
    {
        return $this->contactPersonId;
    }

    /**
     * @param int|null $contactPersonId
     * @return $this
     */
    public function setContactPersonId($contactPersonId)
    {
        $this->contactPersonId = $contactPersonId;

        return $this;
    }

    /**
     * @return PackageType
     */
    public function getPackageType()
    {
        return $this->packageType;
    }

    /**
     * @return $this
     */
    public function setPackageType(PackageType $packageType)
    {
        $this->packageType = $packageType;

        return $this;
    }

    /**
     * @return ParcelDimensionsObject[]
     */
    public function getParcelsDimensions()
    {
        return $this->parcelsDimensions;
    }

    /**
     * @param ParcelDimensionsObject[] $parcelsDimensions
     * @return $this
     */
    public function setParcelsDimensions($parcelsDimensions)
    {
        $this->parcelsDimensions = $parcelsDimensions;

        return $this;
    }

    /**
     * @return int
     */
    public function getServiceId()
    {
        return $this->serviceId;
    }

    /**
     * @param int $serviceId
     * @return $this
     */
    public function setServiceId($serviceId)
    {
        $this->serviceId = $serviceId;

        return $this;
    }

    /**
     * @return AwbPaymentType
     */
    public function getAwbPayment()
    {
        return $this->awbPayment;
    }

    /**
     * @return $this
     */
    public function setAwbPayment(AwbPaymentType $awbPayment)
    {
        $this->awbPayment = $awbPayment;

        return $this;
    }

    /**
     * @return AwbRecipientEntityObject
     */
    public function getAwbRecipient()
    {
        return $this->awbRecipient;
    }

    /**
     * @param AwbRecipientEntityObject $awbRecipient
     * @return $this
     */
    public function setAwbRecipient(AwbRecipientEntityObject|\Sameday\Objects\PostAwb\Request\AwbRecipientEntityObject $awbRecipient)
    {
        $this->awbRecipient = $awbRecipient;

        return $this;
    }

    /**
     * @return float
     */
    public function getInsuredValue()
    {
        return $this->insuredValue;
    }

    /**
     * @param float $insuredValue
     * @return $this
     */
    public function setInsuredValue($insuredValue)
    {
        $this->insuredValue = $insuredValue;

        return $this;
    }

    /**
     * @return float
     */
    public function getCashOnDeliveryAmount()
    {
        return $this->cashOnDeliveryAmount;
    }

    /**
     * @param float $cashOnDeliveryAmount
     * @return $this
     */
    public function setCashOnDeliveryAmount($cashOnDeliveryAmount)
    {
        $this->cashOnDeliveryAmount = $cashOnDeliveryAmount;

        return $this;
    }

    /**
     * @return ThirdPartyPickupEntityObject|null
     */
    public function getThirdPartyPickup()
    {
        return $this->thirdPartyPickup;
    }

    /**
     * @param ThirdPartyPickupEntityObject|null $thirdPartyPickup
     * @return $this
     */
    public function setThirdPartyPickup(ThirdPartyPickupEntityObject $thirdPartyPickup)
    {
        $this->thirdPartyPickup = $thirdPartyPickup;

        return $this;
    }

    /**
     * @return int[]
     */
    public function getServiceTaxIds()
    {
        return $this->serviceTaxIds;
    }

    /**
     * @param int[] $serviceTaxIds
     * @return $this
     */
    public function setServiceTaxIds($serviceTaxIds)
    {
        $this->serviceTaxIds = $serviceTaxIds;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param $currency
     * @return $this
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;

        return $this;
    }
}
