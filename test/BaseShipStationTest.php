<?php

declare(strict_types=1);

namespace Orderadmin\Integrations\ShipStationTest;

use Doctrine\ORM\EntityManager;
use Orderadmin\ApplicationTest\BaseTraits\BaseTrait;
use Orderadmin\ApplicationTest\BaseTraits\LoggerTrait;
use PHPUnit\Framework\TestCase;

use function dirname;

// phpcs:ignore WebimpressCodingStandard.NamingConventions.AbstractClass.Prefix
abstract class BaseShipStationTest extends TestCase
{
    use BaseTrait;
    use LoggerTrait;

    protected EntityManager $objectManager;

    public function setUp(): void
    {
        $this->setFixturePath(dirname(__FILE__))->createLogger();

        // Creating mock for the entity manager
        $objectManager       = $this->createMock(EntityManager::class);
        $this->objectManager = $objectManager;
    }
}
