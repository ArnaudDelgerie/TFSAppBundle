<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Doctrine;

/**
 * The pragma statements SqlitePragmaDriver issues on every SQLite connection, and the values
 * tfsapp:doctor reports back. Kept free of any Doctrine\DBAL dependency so DoctorCommand can read
 * these values in apps that don't have doctrine/dbal installed at all.
 */
final class SqlitePragmas
{
    public const BUSY_TIMEOUT_MS = 5000;

    /**
     * @return list<string>
     */
    public static function statements(): array
    {
        return [
            'PRAGMA journal_mode=WAL',
            'PRAGMA synchronous=NORMAL',
            sprintf('PRAGMA busy_timeout=%d', self::BUSY_TIMEOUT_MS),
        ];
    }
}
