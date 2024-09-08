<?php
/**
 * Created by PhpStorm.
 * User: IqCreative - Acho
 * Date: 7/17/2019
 * Time: 10:13 AM
 */

namespace Octava\Integrations\Sameday\Service\DeliveryServices;

use Octava\Integrations\Sameday\Service\Integration;
use Orderadmin\DeliveryServices\Entity\Rate;
use Orderadmin\Locations\Model\LocalityManagerAwareInterface;
use Orderadmin\Locations\Traits\LocalityManagerAwareTrait;

class Loader extends Integration implements
    LocalityManagerAwareInterface
{
    use LocalityManagerAwareTrait;

    public function loadRates()
    {
        $ratesData = [
            [
                'name'  => 'Sameday Default rate',
                'extId' => '1',
                'type'  => Rate::TYPE_COURIER,
            ],
        ];

        foreach ($ratesData as $rateData) {
            /** @var Rate $rate */
            $rate = $this->getObjectManager()->getRepository(
                Rate::class
            )->findOneBy(
                [
                    'extId' => $rateData['extId'],
                    'deliveryService' => $this->getDeliveryService()
                ]
            );
            if (! empty($rate)) {
                $this->getLogger()->info(
                    sprintf(
                        $this->getTranslator()
                            ->translate(
                                'Rate with  %s(%s) already exists.'
                            ),
                        $rate->getName(),
                        $rate->getExtId()
                    )
                );
                continue;
            } else {
                $this->getLogger()->info(
                    sprintf(
                        $this->getTranslator()
                            ->translate(
                                'Saving new rate %s(%s).'
                            ),
                        $rateData['name'],
                        $rateData['extId']
                    )
                );
            }

            $data = [
                'name'              => $rateData['name'],
                'extId'             => $rateData['extId'],
                'type'              => $rateData['type'],
                'deliveryService'   => $this->getDeliveryService()
            ];

            $this->getDeliveryServiceManager()->saveRate($data);
        }

        return $ratesData;
    }
}
