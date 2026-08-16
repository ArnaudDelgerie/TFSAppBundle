<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver;
use Doctrine\DBAL\Driver\Middleware;

/**
 * Wraps SQLite drivers only — DATABASE_URL is always SQLite per the hub's CONTRACT.md §3, but a
 * project pointed at anything else must be untouched.
 */
final class SqlitePragmaMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        if (!$driver instanceof AbstractSQLiteDriver) {
            return $driver;
        }

        return new SqlitePragmaDriver($driver);
    }
}
