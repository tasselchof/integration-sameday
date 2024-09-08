<?php
/**
 * Created by PhpStorm.
 * User: tasselchof
 * Date: 16.12.15
 * Time: 2:37
 */

namespace Octava\Integrations\Sameday\Service\DeliveryServices;

use Doctrine\Common\Collections\Criteria;
use Laminas\Http\Request;
use Octava\Integrations\Sameday\Exception\SamedayException;
use Octava\Integrations\Sameday\Service\Integration;
use Orderadmin\Application\Model\Manager\OrderadminManagerAwareInterface;
use Orderadmin\Application\Traits\OrderadminManagerAwareTrait;
use Orderadmin\DeliveryServices\Entity\DeliveryRequest;
use Orderadmin\DeliveryServices\Entity\Log\Preprocessing\TaskLogEntry;
use Orderadmin\DeliveryServices\Entity\Processing\Task;
use Orderadmin\DeliveryServices\Exception\DeliveryRequestException;
use Orderadmin\DeliveryServices\Model\Feature\Integration\LabelsProviderInterface;
use Orderadmin\DeliveryServices\Module;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\Filter\FilterException;
use setasign\Fpdi\PdfParser\PdfParserException;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfTypeException;
use setasign\Fpdi\PdfReader\PdfReaderException;

class Labels extends Integration implements
    LabelsProviderInterface,
    OrderadminManagerAwareInterface
{
    use OrderadminManagerAwareTrait;

    /**
     * @throws PdfTypeException
     * @throws CrossReferenceException
     * @throws PdfReaderException
     * @throws PdfParserException
     * @throws FilterException
     */
    public function mergeLabels(array $labels): string
    {
        $pages[] = null;
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm', 'A6');
        foreach ($labels as $label) {
            if (empty($label['labelData'])) {
                throw new SamedayException('The task label data is empty');
            }

            $stream = StreamReader::createByString(
                base64_decode($label['labelData'])
            );

            $pdf->setSourceFile($stream);
            $pages[] = $pdf->importPage(1);
            $pdf->AddPage();
            $pdf->useImportedPage(
                $pages[array_search($pdf->importPage(1), $pages)]
            );
        }

        $output = $pdf->Output('', 'S');
        $mergedPdfBase64 = base64_encode($output);

        return $mergedPdfBase64;
    }

    public function voidLabel(
        array $shipmentIDs
    ): array {
        $res = $this->setRequest(
            $shipmentIDs
        )->request('/shipments/voidlabel', [], Request::METHOD_POST)
            ->getResult();

        $this->getLogger()->warn(
            'Voiding of label success'
        );

        return $res;
    }

    public function getLabels(
        DeliveryRequest $deliveryRequest
    ): string {
        $tasks = $deliveryRequest->getTasks()->matching(
            Criteria::create()->where(
                Criteria::expr()->isNull('source')
            )->orderBy(['created', 'DESC'])
        );

        if (empty($tasks)) {
            throw new SamedayException(
                'Processing tasks for Sameday not found'
            );
        }

//        $taskHistory = $this->getObjectManager()
//            ->getRepository(
//                TaskLogEntry::class
//            );
//
//        $lastTask = null;
//        $latestTime = null;
//
//        foreach ($tasks as $task) {
//            $taskHistoryItems = $taskHistory->getLogEntries($task);
//
//            foreach ($taskHistoryItems as $taskHistoryItem) {
//                $taskHistoryData = $taskHistoryItem->getData();
//
//                if (empty($taskHistoryData['state']) || $taskHistoryData['state'] != Task::STATE_CLOSED) {
//                    continue;
//                } else {
//                    $loggedAt = $taskHistoryItem->getLoggedAt();
//
//                    if ($loggedAt > $latestTime) {
//                        $latestTime = $loggedAt;
//                        $lastTask = $task;
//                    }
//
//                    break;
//                }
//            }
//        }

        /* @var Task $task */
        $task = $tasks->first();

        $exportResult = $task->getExportResult();
        if (empty($exportResult[0]['labelData'])) {
            $exportResult = null;
            $taskHistory = $this->getObjectManager()
                ->getRepository(
                    TaskLogEntry::class
                );

            $taskHistoryItems
                = $taskHistory->getLogEntries(
                    $task
                );
            foreach ($taskHistoryItems as $taskHistoryItem) {
                $taskHistoryData = $taskHistoryItem->getData();
                if (empty($taskHistoryData['state'])
                    || ($taskHistoryData['state'] !=
                        (Task::STATE_CANCEL || Task::STATE_CLOSED))
                ) {
                    continue;
                } elseif ($taskHistoryData['state'] == Task::STATE_CANCEL) {
                    throw new SamedayException('Label was canceled');
                } elseif ($taskHistoryData['state'] == Task::STATE_CLOSED
                    && ! empty($taskHistoryData['exportResult'])
                ) {
                    $exportResult = $taskHistoryData['exportResult'];
                    break;
                }
            }
            if (empty($exportResult)) {
                throw new SamedayException('Label data is wrong');
            }
        }

        if (count($exportResult) > 1) {
            $label = $this->mergeLabels($exportResult);
        } else {
            $label = $exportResult[0]['labelData'];
        }

        $path = sprintf(
            "%s/modules/%s/shipment/%s_%s_label.pdf",
            $this->getOrderadminManager()->getBucket(),
            Module::MODULE_ID,
            $deliveryRequest->getId(),
            substr(md5($deliveryRequest->getCreated()->format('c')), 0, 9)
        );

        if (! $file = @file_get_contents($path)) {
            if (empty($label)) {
                throw new DeliveryRequestException('Label is empty');
            }

            $filename = $this->getOrderadminManager()->uploadContent(
                $label,
                $path,
                'application/pdf'
            );

            $file = $label;
        }

        return sprintf('data:application/pdf;base64,%s', $file);
    }
}
