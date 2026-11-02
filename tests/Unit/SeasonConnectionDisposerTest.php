<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SeasonConnectionDisposer;
use App\Support\SeasonLifecycleLock;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class SeasonConnectionDisposerTest extends TestCase
{
    public function test_detaches_both_integrations_without_closing_other_connections(): void
    {
        require_once dirname(__DIR__, 2).'/legacy/config/database.php';
        $oldContainer = Container::getInstance();
        $legacy = new ReflectionProperty(\Game\Config\Database::class, 'instance');
        $oldLegacy = $legacy->getValue();
        try {
            $container = new Container;
            Container::setInstance($container);
            $manager = new DatabaseManager($container, new ConnectionFactory($container));
            $container->instance('db', $manager);
            $unsafe = new DisposerProtocolPdo;
            $healthy = new DisposerProtocolPdo;
            $split = new Connection($unsafe);
            $split->setReadPdo($healthy);
            $onlyUnsafe = new Connection($unsafe);
            $unrelated = new Connection($healthy);
            (new ReflectionProperty($manager, 'connections'))->setValue($manager, [
                'split' => $split, 'unsafe' => $onlyUnsafe, 'healthy' => $unrelated,
            ]);
            $legacy->setValue(null, $unsafe);
            SeasonConnectionDisposer::discard($unsafe);
            $this->assertNull($legacy->getValue());
            $this->assertNull($split->getRawPdo());
            $this->assertSame($healthy, $split->getRawReadPdo());
            $this->assertNull($onlyUnsafe->getRawPdo());
            $this->assertArrayNotHasKey('unsafe', $manager->getConnections());
            $this->assertSame($healthy, $unrelated->getRawPdo());
            $this->assertTrue(SeasonLifecycleLock::isUnsafe($unsafe));
            // A retained external PDO reference is still an object; disposal cannot prove termination.
            $this->assertInstanceOf(PDO::class, $unsafe);
        } finally {
            $legacy->setValue(null, $oldLegacy);
            Container::setInstance($oldContainer);
        }
    }
}

final class DisposerProtocolPdo extends PDO
{
    public function __construct() {}
}
