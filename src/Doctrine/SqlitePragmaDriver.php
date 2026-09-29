<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Doctrine;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use SensitiveParameter;

/**
 * Issues SqlitePragmas::statements() once per connection, right after it's opened. WAL isn't the
 * default Doctrine sets, and without it any writer (the async worker) blocks every reader (the web
 * process) on the same SQLite file, and the app would stop having a second lane. The pragmas are asserted
 * on every connection rather than once at install time: an import or rescue can restore the database
 * file without its -wal twin, so it can arrive back in rollback-journal mode. See CONTRACT.md §3.
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

        foreach (SqlitePragmas::statements() as $statement) {
            $connection->exec($statement);
        }

        return $connection;
    }
}
