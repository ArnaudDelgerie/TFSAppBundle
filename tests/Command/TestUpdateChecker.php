<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Command;

use ArnaudDelgerie\TFSAppBundle\Bridge\UpdateCheckResult;
use ArnaudDelgerie\TFSAppBundle\Bridge\UpdateCheckerInterface;

final class TestUpdateChecker implements UpdateCheckerInterface
{
    public function __construct(private readonly bool $available)
    {
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function check(): UpdateCheckResult
    {
        throw new \LogicException('The doctor row only reads isAvailable().');
    }
}
