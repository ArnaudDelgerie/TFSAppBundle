<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

/**
 * Wraps every driver, SQLite or not — DATABASE_URL is always SQLite per the hub's CONTRACT.md §3, but
 * a project pointed at anything else must be untouched, which SqlitePragmaDriver decides from the
 * connection parameters. The wrap order of the middleware list is not ours to choose (DoctrineBundle
 * sorts it by tag priority, and the app can register its own middlewares), so the driver seen here may
 * already be wrapped by another middleware and reveal nothing about the platform underneath.
 */
final class SqlitePragmaMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new SqlitePragmaDriver($driver);
    }
}
