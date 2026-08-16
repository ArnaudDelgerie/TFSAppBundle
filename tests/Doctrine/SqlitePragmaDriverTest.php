<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Doctrine;

use ArnaudDelgerie\TFSAppBundle\Doctrine\SqlitePragmaDriver;
use ArnaudDelgerie\TFSAppBundle\Doctrine\SqlitePragmas;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver as SQLiteDriver;
use PHPUnit\Framework\TestCase;

final class SqlitePragmaDriverTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'tfs-app-bundle-sqlite-pragma-');
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function testConnectAppliesWalSynchronousNormalAndBusyTimeout(): void
    {
        $driver = new SqlitePragmaDriver(new SQLiteDriver());
        $connection = $driver->connect(['path' => $this->path]);

        self::assertSame('wal', $connection->query('PRAGMA journal_mode')->fetchOne());
        self::assertSame(1, $connection->query('PRAGMA synchronous')->fetchOne());
        self::assertSame(SqlitePragmas::BUSY_TIMEOUT_MS, $connection->query('PRAGMA busy_timeout')->fetchOne());
    }
}
