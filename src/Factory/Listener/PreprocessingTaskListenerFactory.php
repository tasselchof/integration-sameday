<?php

declare(strict_types=1);

namespace Octava\Integration\Sameday\Factory\Listener;

use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Octava\Integration\Sameday\Listener\PreprocessingTaskListener;
use Octava\Integration\Sameday\Service\V2\SamedayClient;

class PreprocessingTaskListenerFactory implements FactoryInterface
{
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): PreprocessingTaskListener
    {
        $samedayClient = $container->get(SamedayClient::class);

        return new PreprocessingTaskListener($samedayClient);
    }
}
