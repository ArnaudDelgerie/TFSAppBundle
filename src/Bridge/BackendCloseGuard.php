<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Bridge;

use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\BridgeProtocolException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\CloseGuardClosingException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\CloseGuardInvalidIdException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\CloseGuardTooManyException;

/**
 * Backend close guards over the loopback bridge (the hub contract's
 * close_guard group, bridge namespace). One guard is one id owned by the
 * code that registered it: registration happens before the vulnerable
 * work starts, removal runs in a finally on normal completion, and
 * simultaneous jobs use distinct ids so finishing one never removes the
 * other's protection.
 *
 * The hub enforces the wire rules itself — a non-empty id of at most 128
 * bytes of UTF-8, 16 guards per app instance — and this client reports
 * those answers rather than second-guessing them, so its own limits can
 * never drift from the hub's.
 */
final class BackendCloseGuard implements BackendCloseGuardInterface
{
    private const REGISTER_PATH = '/close-guard/register';
    private const REMOVE_PATH = '/close-guard/remove';

    public function __construct(private readonly BridgeTransport $transport)
    {
    }

    public function register(string $id): bool
    {
        return $this->send(self::REGISTER_PATH, $id);
    }

    public function remove(string $id): bool
    {
        return $this->send(self::REMOVE_PATH, $id);
    }

    /**
     * Both operations are the same wire exchange: {id} to one route, and
     * only a 200 means the hub acted. No bridge and a gated route are
     * results (false), never exceptions — the hub contract makes
     * unavailability a result — while every refusal that has its own
     * error stays distinguishable from success as a typed exception.
     */
    private function send(string $path, string $id): bool
    {
        if (!$this->transport->isConfigured()) {
            return false;
        }

        $response = $this->transport->request('POST', $path, ['json' => ['id' => $id]]);

        return match ($response->getStatusCode()) {
            200 => true,
            400 => throw CloseGuardInvalidIdException::forId($id), // invalid_body never reaches here: the transport throws it
            404 => false, // the close_guard group did not declare "bridge": its routes are gated
            429 => throw CloseGuardTooManyException::create(),
            503 => throw CloseGuardClosingException::create(),
            default => throw BridgeProtocolException::forStatus($response->getStatusCode()),
        };
    }
}
