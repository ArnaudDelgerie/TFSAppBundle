<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Doctrine;

use Doctrine\DBAL\Driver\AbstractSQLiteDriver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use SensitiveParameter;

/**
 * Issues SqlitePragmas::statements() once per connection, right after it's opened. WAL isn't the
 * default Doctrine sets, and without it any writer (the async worker) blocks every reader (the web
 * process) on the same SQLite file, and the app would stop having a second lane. The pragmas are asserted
 * on every connection rather than once at install time: an import or rescue can restore the database
 * file without its -wal twin, so it can arrive back in rollback-journal mode. See CONTRACT.md §3.
 *
 * Whether this is a SQLite connection is decided from the connection parameters, not from the wrapped
 * driver's class: the middleware list's wrap order is not ours to choose (DoctrineBundle sorts it by
 * tag priority, and the app can register its own middlewares), so the wrapped driver may be another
 * middleware's, whatever the platform underneath.
 */
final class SqlitePragmaDriver extends AbstractDriverMiddleware
{
    /**
     * {@inheritDoc}
     */
    public function connect(
        #[SensitiveParameter]
        array $params,
    ): Connection {
        $connection = parent::connect($params);

        if (!self::isSqlite($params)) {
            return $connection;
        }

        foreach (SqlitePragmas::statements() as $statement) {
            $connection->exec($statement);
        }

        return $connection;
    }

    /**
     * DBAL knows two SQLite drivers — `pdo_sqlite` (which DoctrineBundle maps both `sqlite://` and
     * `sqlite3://` URLs to) and `sqlite3` — and an app may instead name a driverClass directly.
     *
     * @param array<string, mixed> $params
     */
    private static function isSqlite(array $params): bool
    {
        if (isset($params['driver']) && in_array($params['driver'], ['pdo_sqlite', 'sqlite3'], true)) {
            return true;
        }

        return isset($params['driverClass']) && is_subclass_of($params['driverClass'], AbstractSQLiteDriver::class);
    }
}
