<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Doctrine;

use ArnaudDelgerie\TFSAppBundle\Doctrine\SqlitePragmas;
use PHPUnit\Framework\TestCase;

final class SqlitePragmasTest extends TestCase
{
    public function testStatementsSetWalSynchronousNormalAndTheBusyTimeoutConstant(): void
    {
        $statements = SqlitePragmas::statements();

        self::assertSame(
            [
                'PRAGMA journal_mode=WAL',
                'PRAGMA synchronous=NORMAL',
                'PRAGMA busy_timeout=' . SqlitePragmas::BUSY_TIMEOUT_MS,
            ],
            $statements,
        );
    }
}
