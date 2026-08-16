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

    public function testLeavesNonSqliteDriversUntouched(): void
    {
        $driver = new PgSQLDriver();

        self::assertSame($driver, (new SqlitePragmaMiddleware())->wrap($driver));
    }
}
