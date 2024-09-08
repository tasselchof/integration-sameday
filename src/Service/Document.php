<?php
/**
 * Created by PhpStorm.
 * User: mtodo
 * Date: 14.09.20
 * Time: 13:15
 */

namespace Octava\Integrations\Sameday\Service\DeliveryServices;

use Orderadmin\Application\Traits\GearmanManagerAwareTrait;
use Orderadmin\Application\Traits\ViewHelperManagerAwareTrait;
use Orderadmin\DeliveryServices\Entity\Processing\Task;
use Orderadmin\DeliveryServices\Model\Feature\V2\ShipmentProviderInterface;
use Orderadmin\DeliveryServices\Traits\DeliveryServiceV2Trait;

class Document implements
    ShipmentProviderInterface
{
    use GearmanManagerAwareTrait, ViewHelperManagerAwareTrait, DeliveryServiceV2Trait;

    protected Task $task;

    public function prepareTask(Task $task): array
    {
        $this->task = $task;

        return [];
    }

    public function createShipment(): array
    {
        $task = $this->task;
        $result = [];

        return $result;
    }
}
