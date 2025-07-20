<?php

declare(strict_types=1);

namespace Octava\Integration\Sameday\Service;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Dot\DependencyInjection\Attribute\Inject;
use Orderadmin\DeliveryServices\Entity\DeliveryRequest;
use Orderadmin\DeliveryServices\Exception\DeliveryServiceException;
use Orderadmin\DeliveryServices\Model\Feature\V2\LabelsProviderInterface;
use Orderadmin\DeliveryServices\Traits\Feature\ConnectionAwareTrait;
use Psr\Log\LoggerInterface;
use Sameday\Exceptions\SamedayAuthenticationException;
use Sameday\Exceptions\SamedayServerException;
use Sameday\Objects\Types\AwbPdfType;
use Sameday\Requests\SamedayGetAwbPdfRequest;
use Sameday\Sameday;

use function base64_encode;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function sprintf;
use function sys_get_temp_dir;

class Labels implements LabelsProviderInterface
{
    use ConnectionAwareTrait;

    #[Inject(
        EntityManagerInterface::class,
        EntityManager::class,
        LoggerInterface::class
    )]
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected EntityManagerInterface $objectManager,
        protected LoggerInterface $logger
    ) {
    }

    public function getLabels(DeliveryRequest $deliveryRequest, array $options = []): string
    {
        // Set up the integration context
        $this->setCarrierIntegration($deliveryRequest->getIntegration());
        $settings          = $this->source->getSettings();
        $protectedSettings = $this->source->getSettingsProtected();

        if (empty($deliveryRequest->getTrackingNumber())) {
            throw new DeliveryServiceException(
                sprintf(
                    'Delivery request ID %s doesn\'t have tracking number',
                    $deliveryRequest->getId()
                )
            );
        }

        if (empty($protectedSettings['auth']['username']) || empty($protectedSettings['auth']['password'])) {
            throw new DeliveryServiceException(
                sprintf(
                    'Integration ID %s username or password not set',
                    $deliveryRequest->getIntegration()->getId()
                )
            );
        }

        // Create documents directory using PHP temp directory
        $path = sys_get_temp_dir() . '/sameday/documents';
        if (! file_exists($path)) {
            mkdir($path, 0777, true);
        }

        $documentType = $settings['documents-type'] ?? 'A6';

        $fileName = sprintf(
            '%s/%s_%s_%s.pdf',
            $path,
            $deliveryRequest->getId(),
            $deliveryRequest->getTrackingNumber(),
            $documentType
        );

        // Generate or retrieve the PDF
        if (! file_exists($fileName)) {
            $samedayClient = new \Octava\Integration\Sameday\Service\SamedayClient(
                $protectedSettings['auth']['username'],
                $protectedSettings['auth']['password']
            );

            $sameday = new Sameday($samedayClient);
            $data    = new SamedayGetAwbPdfRequest(
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
                        sprintf(
                            'There is problem with printing of PDF with delivery request %s',
                            $deliveryRequest->getId()
                        )
                    );
                }
            } catch (SamedayAuthenticationException $e) {
                $this->logger->error('Sameday authentication failed: ' . $e->getMessage());
                throw new DeliveryServiceException('Sameday authentication failed: ' . $e->getMessage());
            } catch (SamedayServerException $e) {
                $this->logger->error('Sameday server error: ' . $e->getMessage());
                throw new DeliveryServiceException('Sameday server error: ' . $e->getMessage());
            } catch (\Exception $e) {
                $this->logger->error('Failed to generate Sameday label: ' . $e->getMessage());
                throw new DeliveryServiceException($e->getMessage());
            }
        } else {
            $res = file_get_contents($fileName);
        }

        // Return base64 encoded PDF
        return sprintf('data:application/pdf;base64,%s', base64_encode($res));
    }
}
