<?php
/**
 * Created by PhpStorm.
 * User: IqCreative - Acho
 * Date: 7/17/2019
 * Time: 10:13 AM
 */

namespace Octava\Integrations\Sameday\Service\Loader;

use Octava\Integrations\Sameday\Service\SamedayClient;
use Orderadmin\Application\Model\LoggerAwareInterface;
use Orderadmin\Application\Model\Manager\ObjectManagerAwareInterface;
use Orderadmin\Application\Model\Manager\OrderadminManagerAwareInterface;
use Orderadmin\Application\Traits\ConfigManagerAwareTrait;
use Orderadmin\Application\Traits\LoggerAwareTrait;
use Orderadmin\Application\Traits\ObjectManagerAwareTrait;
use Orderadmin\Application\Traits\OrderadminManagerAwareTrait;
use Orderadmin\DeliveryServices\Model\Feature\V2\PaginatorProviderInterface;
use Octava\Integrations\Sameday\Service\Convert;
use Octava\Integrations\Sameday\Service\Integration;
use Orderadmin\DeliveryServices\Entity\Postcode;
use Orderadmin\DeliveryServices\Model\Feature\V2\Loader\ServicePointsProviderInterface;
use Orderadmin\DeliveryServices\Traits\DeliveryServiceV2Trait;
use Orderadmin\DeliveryServices\Traits\Feature\PaginatorTrait;
use Orderadmin\Locations\Entity\Country;
use Orderadmin\Locations\Entity\Locality;
use Orderadmin\Locations\Model\LocalityManagerAwareInterface;
use Orderadmin\Locations\Traits\LocalityManagerAwareTrait;
use Sameday\Requests\SamedayGetOohLocationsRequest;
use Sameday\Sameday;
use Users\Traits\AuthManagerAwareTrait;

class ServicePoints extends Integration implements
    ObjectManagerAwareInterface,
    PaginatorProviderInterface,
    OrderadminManagerAwareInterface,
    LoggerAwareInterface,
    LocalityManagerAwareInterface,
    ServicePointsProviderInterface
{
    use ObjectManagerAwareTrait,
        DeliveryServiceV2Trait,
        AuthManagerAwareTrait,
        ConfigManagerAwareTrait,
        LoggerAwareTrait,
        OrderadminManagerAwareTrait,
        LocalityManagerAwareTrait,
        PaginatorTrait;

    protected array $currentItems = [];

    protected array $resultData = [];

    protected int $count = 0;

    protected array $countries = [
        34 => 25
    ];

    public function count()
    {
        return count($this->currentItems);
    }

    public function loadElements(array $criteria = [], int $page = 1): array
    {
        $settings = $this->getIntegration()->getSettings();
        $samedayClient = new SamedayClient($settings['username'], $settings['password']);
        $sameday = new Sameday($samedayClient);
        $request = new SamedayGetOohLocationsRequest();
        $request->setCountPerPage(100);
        $servicePoints = [];
        $request->setPage($this->getCurrentPageNumber());
        $res = $sameday->getOohLocations($request);
        $oohLocations = json_decode($res->getRawResponse()->getBody(), true)['data'];
        if (!empty($oohLocations)) {
            foreach ($oohLocations as $oohLocation) {
                $servicePoints[] = $oohLocation;
            }
        }
        $this->getLogger()->info(
            sprintf(
                'Found %s service points',
                count($servicePoints)
            )
        );

        $postcodeToLocality = [];
        foreach ($servicePoints as $officeData) {
            $servicePointData = Convert::officeToServicePoint($officeData);

                /** @var Country $country */
                $this->countries[$servicePointData['country']]
                    = $this->getObjectManager()->getRepository(Country::class)
                    ->find($this->countries[$servicePointData['country']]);
                if (empty($this->countries[$servicePointData['country']])) {
                    $this->getLogger()->warn(
                        sprintf(
                            'Country (code %s) not found, service point %s will be skipped',
                            $servicePointData['country'],
                            $servicePointData['extId']
                        )
                    );

                    continue;
                }

            $servicePointData['country']
                = $this->countries[$servicePointData['country']];

            if (! empty($servicePointData['locality'])) {
                if (! empty($servicePointData['locality']['postcode'])) {
                    $postcode = $servicePointData['locality']['postcode'];

                    $postcodeCode = sprintf(
                        '%s%s',
                        $servicePointData['country'],
                        $postcode
                    );
                    if (empty($postcodeToLocality[$postcodeCode])) {
                        /** @var Postcode $postcode */
                        $postcode = $this->getObjectManager()->getRepository(
                            Postcode::class
                        )->findOneBy([
                            'country' => $servicePointData['country'],
                            'extId'   => $postcode,
                        ]);

                        if (! empty($postcode)) {
                            $postcodeToLocality[$postcodeCode]
                                = $postcode->getLocality();
                        }
                    }

                    if (! empty($postcodeToLocality[$postcodeCode])) {
                        $servicePointData['locality']
                            = $postcodeToLocality[$postcodeCode];

                        $this->getLogger()->info(
                            sprintf(
                                'The locality was found: %s.',
                                $postcodeToLocality[$postcodeCode]
                            )
                        );
                    }
                }

                if (! $servicePointData['locality'] instanceof Locality) {
                    unset($servicePointData['locality']);
                }
            }

            // Convert GEO coordinates
            if (! empty($servicePointData['geo']['latitude'])
                && ! empty($servicePointData['geo']['longitude'])
            ) {
                $servicePointData['geo'] = sprintf(
                    'POINT(%s %s)',
                    $servicePointData['geo']['longitude'],
                    $servicePointData['geo']['latitude']
                );
            } else {
                unset($servicePointData['geo']);
            }
            $this->currentItems[] = $servicePointData;
        }
        $this->setPageCount($res->getPages());
        return $this->currentItems;
    }

    /**
     * @return Country
     */
    public function getCountry()
    {
        return $this->getDeliveryService()->getCountry();
    }
}
