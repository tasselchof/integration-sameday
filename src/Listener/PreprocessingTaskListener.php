<?php

declare(strict_types=1);

namespace Octava\Integration\Sameday\Listener;

use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Octava\Integration\Sameday\Exception\SamedayException;
use Octava\Integration\Sameday\Service\V2\SamedayClient;

class PreprocessingTaskListener implements ListenerAggregateInterface
{
    private SamedayClient $client;
    private array $listeners = [];

    public function __construct(SamedayClient $client)
    {
        $this->client = $client;
    }

    public function attach(EventManagerInterface $events, $priority = 1)
    {
        // Attach to preprocessing task events
        $this->listeners[] = $events->attach('preprocessing.task', [$this, '__invoke'], $priority);
    }

    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {
            $events->detach($listener);
            unset($this->listeners[$index]);
        }
    }

    public function __invoke($event): void
    {
        try {
            // Handle preprocessing tasks for Sameday integration
            // This could include validation, data preparation, etc.
            $taskData = $event->getData();

            // Example: Validate shipment data before processing
            if (isset($taskData['shipment'])) {
                $this->validateShipmentData($taskData['shipment']);
            }
        } catch (SamedayException $e) {
            $event->addError($e->getMessage());
        }
    }

    private function validateShipmentData(array $shipmentData): void
    {
        $requiredFields = ['recipient', 'sender', 'package_weight'];

        foreach ($requiredFields as $field) {
            if (! isset($shipmentData[$field])) {
                throw new SamedayException("Missing required field: {$field}");
            }
        }
    }
}
