<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Doctrine;

use ArnaudDelgerie\TFSAppBundle\Doctrine\SqlitePragmaMiddleware;
use ArnaudDelgerie\TFSAppBundle\Doctrine\SqlitePragmas;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware as LoggingMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class SqlitePragmaMiddlewareChainTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'tfs-app-bundle-sqlite-pragma-');
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        @unlink($this->path.'-wal');
        @unlink($this->path.'-shm');
    }

    public function testPragmasApplyWhenAnotherMiddlewareWrapsTheDriverFirst(): void
    {
        $config = (new Configuration())
            ->setMiddlewares([
                new LoggingMiddleware(new NullLogger()),
                new SqlitePragmaMiddleware(),
            ]);

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path' => $this->path,
        ], $config);

        $connection->executeQuery('SELECT 1');

        self::assertSame('wal', $connection->executeQuery('PRAGMA journal_mode')->fetchOne());
        self::assertSame(SqlitePragmas::BUSY_TIMEOUT_MS, $connection->executeQuery('PRAGMA busy_timeout')->fetchOne());
    }
}
