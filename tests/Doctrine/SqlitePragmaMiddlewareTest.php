<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Doctrine;

use ArnaudDelgerie\TFSAppBundle\Doctrine\SqlitePragmaDriver;
use ArnaudDelgerie\TFSAppBundle\Doctrine\SqlitePragmaMiddleware;
use Doctrine\DBAL\Driver\PDO\PgSQL\Driver as PgSQLDriver;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver as SQLiteDriver;
use PHPUnit\Framework\TestCase;

final class SqlitePragmaMiddlewareTest extends TestCase
{
    public function testWrapsSqliteDrivers(): void
    {
        $wrapped = (new SqlitePragmaMiddleware())->wrap(new SQLiteDriver());

        self::assertInstanceOf(SqlitePragmaDriver::class, $wrapped);
    }

    /**
     * The wrap order of the middleware list is not ours to choose, so the middleware cannot tell a
     * non-SQLite driver from another middleware's wrapper around a SQLite one: it wraps everything
     * and leaves the platform decision to SqlitePragmaDriver, which sees the connection parameters.
     */
    public function testWrapsNonSqliteDriversToo(): void
    {
        $wrapped = (new SqlitePragmaMiddleware())->wrap(new PgSQLDriver());

        self::assertInstanceOf(SqlitePragmaDriver::class, $wrapped);
    }
}
