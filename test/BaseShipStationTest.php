<?php

namespace Orderadmin\Integrations\ShipStationTest;

use Doctrine\ORM\EntityManager;
use Laminas\Hydrator\ClassMethodsHydrator;
use Laminas\Log\Filter\Priority;
use Laminas\Log\Logger;
use Laminas\Log\Writer\Stream;
use Orderadmin\ApplicationTest\BaseTraits\BaseTrait;
use Orderadmin\ApplicationTest\BaseTraits\LoggerTrait;
use Orderadmin\Locations\Entity\Area;
use PHPUnit\Framework\TestCase;
use function dirname;
use function file_get_contents;
use function json_decode;

// phpcs:ignore WebimpressCodingStandard.NamingConventions.AbstractClass.Prefix
abstract class BaseShipStationTest extends TestCase
{
    use BaseTrait, LoggerTrait;

    protected EntityManager $objectManager;

    public function setUp(): void
    {
        $this->setFixturePath(dirname(__FILE__))->createLogger();

        // Creating mock for the entity manager
        $objectManager = $this->createMock(EntityManager::class);
        $this->objectManager = $objectManager;
    }
}
