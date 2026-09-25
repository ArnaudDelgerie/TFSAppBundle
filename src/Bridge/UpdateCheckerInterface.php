<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Bridge;

use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\BridgeUnavailableException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\UpdateNotEnabledException;

interface UpdateCheckerInterface
{
    /**
     * Never throws: reports whether the update route itself is enabled, by
     * probing the hub's read-only GET /update/check (200 for an enabled
     * "update" group, 404 for a disabled one, no network refresh). Unlike
     * SecretStoreInterface::isAvailable(), it does call the bridge, but an
     * absent transport or a failed probe simply reports unavailable — a
     * disabled group only throws via check() below, for a direct caller.
     */
    public function isAvailable(): bool;

    /**
     * @throws BridgeUnavailableException
     * @throws UpdateNotEnabledException
     */
    public function check(): UpdateCheckResult;
}
