<?php
/**
 * Created by PhpStorm.
 * User: tasselchof
 * Date: 16.12.15
 * Time: 2:37
 */

namespace Octava\Integrations\Sameday\Service;

use Orderadmin\DeliveryServices\Entity\DeliveryRequest;
use Orderadmin\DeliveryServices\Exception\DeliveryServiceException;
use Orderadmin\DeliveryServices\Model\Feature\Integration\LabelsProviderInterface;
use Sameday\Objects\Types\AwbPdfType;
use Sameday\Requests\SamedayGetAwbPdfRequest;
use Sameday\Sameday;

class Labels extends Integration implements
    LabelsProviderInterface
{
    public function getLabels(
        DeliveryRequest $deliveryRequest
    ): string
    {
        if (empty($deliveryRequest->getTrackingNumber())) {
            throw new DeliveryServiceException(
                $this->getTranslator()->translate(
                    sprintf(
                        'Delivery request ID %s doesn\'t have tracking number',
                        $deliveryRequest->getId()
                    )
                )
            );
        }

        $path = $this->getConfig()['orderadmin']['data_path']
            . '/DeliveryServices/'
            . \Octava\Integrations\Sameday\Module::DELIVERY_SERVICE
            . '/Documents';

        if (! file_exists($path)) {
            mkdir($path, 0777, true);
        }

        $settings = $this->getDeliveryServiceManager()
            ->getIntegrationSettings($deliveryRequest->getIntegration());

        if (empty($settings['username']) || empty($settings['password'])) {
            throw new DeliveryServiceException(
                $this->getTranslator()->translate(
                    sprintf(
                        'Integration ID %s username, password or label type not set',
                        $deliveryRequest->getIntegration()->getId()
                    )
                )
            );
        }

        $documentType = $settings['documents-type'] ?? 'A6';

        $fileName = sprintf(
            '%s/%s_%s_%s.pdf',
            $path,
            $deliveryRequest->getId(),
            $deliveryRequest->getTrackingNumber(),
            $documentType
        );

        if (1 || ! file_exists($fileName)) {

            $samedayClient = new SamedayClient($settings['username'], $settings['password']);
            $sameday = new Sameday($samedayClient);
            $data = new SamedayGetAwbPdfRequest(
                $deliveryRequest->getTrackingNumber(),
                new AwbPdfType($documentType)
            );
            try {
                $res = $sameday->getAwbPdf($data);
                $res = $res->getRawResponse()->getBody();

                if (! empty($res)) {
                    file_put_contents($fileName, $res);
                } else {
                    throw new DeliveryServiceException(
                        $this->getTranslator()->translate(
                            sprintf(
                                'There is problem with printing of PFD with delivery request %s',
                                $deliveryRequest->getSender()
                            )
                        )
                    );
                }
            } catch (\Exception $e) {
                throw new DeliveryServiceException($e->getMessage());
            }
        } else {
            $res = file_get_contents($fileName);
        }

        $res = sprintf('data:application/pdf;base64,%s', base64_encode($res));

        return $res;
    }
}
