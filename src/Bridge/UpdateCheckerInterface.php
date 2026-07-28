<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Bridge;

use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\BridgeUnavailableException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\UpdateNotEnabledException;

interface UpdateCheckerInterface
{
    /**
     * Never throws and never calls the network: reports only whether the
     * bridge transport is present (TFS_BRIDGE_URL set). Unlike
     * SecretStoreInterface::isAvailable(), it says nothing about whether the
     * "update" group itself is enabled — there is no side-effect-free probe
     * for that, so a disabled group only surfaces via check() below.
     */
    public function isAvailable(): bool;

    /**
     * @throws BridgeUnavailableException
     * @throws UpdateNotEnabledException
     */
    public function check(): UpdateCheckResult;
}
