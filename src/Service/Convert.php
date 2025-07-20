<?php

declare(strict_types=1);

namespace Octava\Integrations\Sameday\Service;

use Octava\Integrations\Sameday\Exception\SamedayException;
use Orderadmin\DeliveryServices\Entity\DeliveryService;
use Orderadmin\DeliveryServices\Entity\ServicePoint;
use Orderadmin\Integrations\Entity\AbstractSource;
use Orderadmin\Integrations\Entity\Products\ExpectedOffer;
use Orderadmin\Integrations\Entity\Source;
use Orderadmin\Integrations\Service\AbstractConverter;
use Orderadmin\Products\Entity\AbstractOrder;
use Orderadmin\Products\Entity\Order\OrderProduct;
use Orderadmin\Products\Entity\Product\Offer;
use Orderadmin\Products\Entity\Shop;
use Users\Entity\User;

use function array_flip;
use function join;
use function json_encode;
use function sprintf;
use function trim;

class Convert extends AbstractConverter
{
    protected array $deliveryServicesMatrix
        = [
            'fedex' => 'fedex',
            'dhl'   => 'dhl_express_worldwide',
            'ups'   => 'ups_walleted',
            'usps'  => 'stamps_com',
        ];

    protected array $deliveryServicesRatesMatrix
        = [
            'usps'  => [
                'first_class'                              => 'usps_first_class',
                'usps_first_class_mail'                    => 'usps_first_class_mail',
                'usps_first_class_mail_international'      => 'usps_first_class_mail_international',
                'usps_media_mail'                          => 'usps_media_mail',
                'usps_parcel_select'                       => 'usps_parcel_select',
                'usps_priority_mail'                       => 'usps_priority_mail',
                'usps_priority_mail_express'               => 'usps_priority_mail_express',
                'usps_priority_mail_express_international' => 'usps_priority_mail_express_international',
                'usps_priority_mail_international'         => 'usps_priority_mail_international',
                'usps_ground_advantage'                    => 'GROUND_ADVANTAGE',
            ],
            'ups'   => [
                'ups_2nd_day_air'            => 'ups_2nd_day_air',
                'ups_2nd_day_air_am'         => 'ups_2nd_day_air_am',
                'ups_3_day_select'           => 'ups_3_day_select',
                'ups_ground'                 => 'ups_ground',
                'ups_ground_international'   => 'ups_ground_international',
                'ups_ground_saver'           => 'ups_ground_saver',
                'ups_next_day_air'           => 'ups_next_day_air',
                'ups_next_day_air_early_am'  => 'ups_next_day_air_early_am',
                'ups_next_day_air_saver'     => 'ups_next_day_air_saver',
                'ups_standard_international' => 'ups_standard_international',
                'ups_worldwide_expedited'    => 'ups_worldwide_expedited',
                'ups_worldwide_express'      => 'ups_worldwide_express',
                'ups_worldwide_express_plus' => 'ups_worldwide_express_plus',
                'ups_worldwide_saver'        => 'ups_worldwide_saver',
            ],
            'fedex' => [
                'fedex_1_day_freight'                  => 'fedex_1_day_freight',
                'fedex_2day'                           => 'fedex_2day',
                'fedex_2day_am'                        => 'fedex_2day_am',
                'fedex_2_day_freight'                  => 'fedex_2_day_freight',
                'fedex_3_day_freight'                  => 'fedex_3_day_freight',
                'fedex_express_saver'                  => 'fedex_express_saver',
                'fedex_first_overnight'                => 'fedex_first_overnight',
                'fedex_first_overnight_freight'        => 'fedex_first_overnight_freight',
                'fedex_ground'                         => 'fedex_ground',
                'fedex_ground_international'           => 'fedex_ground_international',
                'fedex_home_delivery'                  => 'fedex_home_delivery',
                'fedex_international_connect_plus'     => 'fedex_international_connect_plus',
                'fedex_international_economy'          => 'fedex_international_economy',
                'fedex_international_economy_freight'  => 'fedex_international_economy_freight',
                'fedex_international_first'            => 'fedex_international_first',
                'fedex_international_priority'         => 'fedex_international_priority',
                'fedex_international_priority_express' => 'fedex_international_priority_express',
                'fedex_international_priority_freight' => 'fedex_international_priority_freight',
                'fedex_priority_overnight'             => 'fedex_priority_overnight',
                'fedex_standard_overnight'             => 'fedex_standard_overnight',
            ],
        ];

    public static function officeToServicePoint(
        array $officeData
    ): array {
        return [
            'extId'          => $officeData['oohId'],
            'country'        => $officeData['countryId'],
            'locality'       => [
                'postcode' => $officeData['postalCode'],
            ],
            'name'           => $officeData['name'],
            'raw'            => $officeData,
            'type'           => $officeData['oohType'] == 1
                ? ServicePoint::TYPE_SERVICE_POINT
                : ServicePoint::TYPE_SELF_SERVICE_POINT,
            'rawAddress'     => trim($officeData['city'] . ', ' . $officeData['address']),
            'rawDescription' => json_encode($officeData['schedule'], true),
            'geo'            => [
                'latitude'  => $officeData['lat'],
                'longitude' => $officeData['lng'],
            ],
        ];
    }

    public static function productToExpectedOffer(
        Shop $shop,
        Source $source,
        array $offerData
    ): array {
        $oaOfferData = [
            'shop'            => $shop->getId(),
            'extId'           => $offerData['productId'],
            'name'            => $offerData['name'],
            'price'           => $offerData['price'],
            'purchasingPrice' => $offerData['price'],
            'weight'          => $offerData['weightOz'] * 28.35,
            'dimensions'      => [
                'x' => $offerData['length'],
                'y' => $offerData['width'],
                'z' => $offerData['height'],
            ],
            'state'           => Offer::STATE_ACTIVE,
            'type'            => Offer::TYPE_SIMPLE,
            'raw'             => $offerData,
        ];

        return [
            'shop'   => $shop,
            'extId'  => $offerData['sku'],
            'source' => $source,
            'state'  => ExpectedOffer::STATE_NEW,
            'raw'    => [
                'rawData'   => $offerData,
                'offerData' => $oaOfferData,
            ],
        ];
    }

    public static function orderToOrder(Source $source, Shop $shop, array $order): array
    {
        $orderData = [
            'shop'          => $shop,
            'extId'         => $order['orderId'],
            'date'          => $order['createDate'],
            'clientId'      => $order['orderNumber'],
            'recipientName' => $order['shipTo']['name'],
            'email'         => ! empty($order['customerEmail'])
                ? $order['customerEmail'] : null,
            'orderPrice'    => $order['orderTotal'],
            'totalPrice'    => $order['orderTotal'],
            'comment'       => ! empty($order['internalNotes'])
                ? $order['internalNotes']
                : (
                ! empty($order['customerNotes'])
                    ? $order['customerNotes'] : null
                ),
            'source'        => $source,
            'raw'           => $order,
        ];

        if (
            ! empty(
                $source->getSettings()['Products']['options']['order-auto-confirm']
            )
        ) {
            $orderData['eav']['order-confirmed'] = true;
        }

        if (! empty($order['shipDate'])) {
            $orderData['shipDate'] = $order['shipDate'];
        }

        $orderData['paymentState'] = AbstractOrder::PAYMENT_STATE_NOT_PAID;

        return $orderData;
    }

    public static function orderToProfile(User $owner, array $order): array
    {
        $profileData = [
            'owner' => $owner->getId(),
            'extId' => $order['customerId'],
            'name'  => $order['shipTo']['name'],
            'phone' => ! empty($order['shipTo']['phone'])
                ? $order['shipTo']['phone'] : null,
            'email' => $order['customerEmail'] ?? null,
            'raw'   => $order['shipTo'],
        ];

        if (! empty($order['shipTo']['country'])) {
            $profileData['country'] = [
                'A2' => $order['shipTo']['country'],
            ];
        }

        return $profileData;
    }

    public static function orderToAddress(array $order): array
    {
        return [
            'postcode'  => isset($order['shipTo']['postalCode'])
                ? trim($order['shipTo']['postalCode'])
                : null,
            'locality'  => [
                'name' => $order['shipTo']['city'] ?? null,
            ],
            'street'    => $order['shipTo']['street1'] ?? null,
            'house'     => $order['shipTo']['street2'] ?? null,
            'block'     => $order['shipTo']['street3'] ?? null,
            'extId'     => $order['orderId'],
            'notFormal' => join(
                ', ',
                [
                    $order['shipTo']['city'],
                    $order['shipTo']['postalCode'],
                    $order['shipTo']['street1'],
                    $order['shipTo']['street2'],
                    $order['shipTo']['street3'],
                ]
            ),
        ];
    }

    public static function orderToOrderProducts(Shop $shop, array $order): array
    {
        $productsData = [];
        foreach ($order['items'] as $item) {
            $productOffer = [
                'shop'            => $shop,
                'extId'           => $item['productId'],
                'article'         => null,
                'name'            => $item['name'],
                'purchasingPrice' => ! empty($item['unitPrice'])
                    ? $item['unitPrice'] : null,
                'price'           => ! empty($item['unitPrice'])
                    ? $item['unitPrice'] : null,
                'sku'             => $item['sku'],
                'dimensions'      => [],
                'type'            => Offer::TYPE_SIMPLE,
                'raw'             => $item,
            ];

            $productsData[] = [
                'productOffer' => $productOffer,
                'extId'        => $item['orderItemId'],
                'count'        => $item['quantity'],
                'state'        => OrderProduct::STATE_ACTIVE,
                'price'        => ! empty($item['unitPrice'])
                    ? (int) $item['unitPrice']
                    : 0,
                'total'        => $item['quantity']
                    * $item['unitPrice'],
            ];
        }

        return $productsData;
    }

    // Dictionary data conversion
    public static function dictionaryStoresToShop(
        AbstractSource $source,
        array $storeData
    ): array {
        return [
            'name'   => $storeData['storeName'],
            'extId'  => $storeData['storeId'],
            'type'   => Source\Dictionary::TYPE_SHOP,
            'state'  => Source\Dictionary::STATE_ACTIVE,
            'source' => $source,
            'raw'    => $storeData,
        ];
    }

    public static function dictionaryWarehousesToWarehouses(
        AbstractSource $source,
        array $warehouseData
    ): array {
        return [
            'name'   => $warehouseData['warehouseName'],
            'extId'  => $warehouseData['warehouseId'],
            'type'   => Source\Dictionary::TYPE_WAREHOUSE,
            'state'  => Source\Dictionary::STATE_ACTIVE,
            'source' => $source,
            'raw'    => $warehouseData,
        ];
    }

    public static function dictionaryStatusToOrderStates(
        AbstractSource $source,
        array $orderStatusData
    ): array {
        return [
            'name'   => $orderStatusData['name'],
            'extId'  => $orderStatusData['code'],
            'type'   => Source\Dictionary::TYPE_ORDER_STATE,
            'state'  => Source\Dictionary::STATE_ACTIVE,
            'source' => $source,
            'raw'    => $orderStatusData,
        ];
    }

    public function convertDeliveryServiceExtId(
        string $extId
    ): ?string {
        if (! empty($this->deliveryServicesMatrix[$extId])) {
            return $this->deliveryServicesMatrix[$extId];
        }

        throw new SamedayException(
            sprintf('Delivery service with external id "%s" not mapped', $extId)
        );
    }

    public function convertDeliveryServiceRateExtId(
        DeliveryService $deliveryService,
        string $extId,
        bool $reverse = false
    ): ?string {
        if (
            ! empty(
                $this->deliveryServicesRatesMatrix[$deliveryService->getExtId()]
            )
        ) {
            $a = $this->deliveryServicesRatesMatrix[$deliveryService->getExtId(
            )];
            if ($reverse) {
                $a = array_flip($a);
            }

            if (isset($a[$extId])) {
                return $a[$extId];
            }
        }

        throw new SamedayException(
            sprintf('Delivery service with external id "%s" not mapped', $extId)
        );
    }
}
