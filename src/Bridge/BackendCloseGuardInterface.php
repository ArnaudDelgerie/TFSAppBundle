<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Bridge;

use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\BridgePayloadTooLargeException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\BridgeProtocolException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\CloseGuardClosingException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\CloseGuardInvalidIdException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\CloseGuardTooManyException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

interface BackendCloseGuardInterface
{
    /**
     * True only after the hub acknowledged the registration (HTTP 200):
     * the guard exists for this launch. False means no guard was
     * installed — no bridge at all (no "actions" group declared "bridge"
     * in tfsapp.config.json), or the close_guard group's bridge routes
     * are gated with 404 (another group started the bridge). Never
     * mistake a configured bridge for an installed guard, and never probe
     * availability by registering a sacrificial guard: this call is the
     * probe.
     *
     * @throws CloseGuardInvalidIdException    the hub rejected the id (400 invalid_id)
     * @throws CloseGuardTooManyException      the hub's guard capacity is exhausted (429 too_many_guards)
     * @throws CloseGuardClosingException      shutdown has committed, the namespace is closed (503 closing)
     * @throws BridgeProtocolException         authentication failed (401), or the body was invalid (400 invalid_body)
     * @throws BridgePayloadTooLargeException  the body exceeded the transport cap (413)
     * @throws TransportExceptionInterface     the bridge could not be reached at all
     */
    public function register(string $id): bool;

    /**
     * Removes a guard this code registered. Idempotent: removing an
     * absent id is acknowledged the same way (HTTP 200). The same result
     * and error table as register(), including closing — once shutdown
     * has committed, removals are refused like registrations.
     *
     * @throws CloseGuardInvalidIdException
     * @throws CloseGuardTooManyException
     * @throws CloseGuardClosingException
     * @throws BridgeProtocolException
     * @throws BridgePayloadTooLargeException
     * @throws TransportExceptionInterface
     */
    public function remove(string $id): bool;
}
