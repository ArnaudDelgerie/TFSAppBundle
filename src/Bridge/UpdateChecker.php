<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Bridge;

use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\UpdateNotEnabledException;

final class UpdateChecker implements UpdateCheckerInterface
{
    public function __construct(private readonly BridgeTransport $transport)
    {
    }

    /**
     * The probe is read-only: the hub answers 200 for an enabled "update"
     * group and 404 for a disabled one, without refreshing its release
     * index. A disabled group is not an error here — that is what is being
     * reported — so unlike check() nothing throws and no typed exception
     * is mapped: absent bridge, transport failure or any non-200 answer
     * all mean unavailable.
     */
    public function isAvailable(): bool
    {
        if (!$this->transport->isConfigured()) {
            return false;
        }

        try {
            return 200 === $this->transport->request('GET', '/update/check')->getStatusCode();
        } catch (\Throwable) {
            return false;
        }
    }

    public function check(): UpdateCheckResult
    {
        $response = $this->transport->request('GET', '/update/check');

        if (404 === $response->getStatusCode()) {
            throw UpdateNotEnabledException::create();
        }

        /**
         * @var array{
         *     status: string,
         *     current?: string,
         *     latest?: string,
         *     update_available?: bool,
         *     release_url?: string,
         *     notes?: string,
         *     reason?: string,
         * } $data
         */
        $data = $response->toArray();

        return 'ok' === $data['status']
            ? UpdateCheckResult::ok($data['current'], $data['latest'], $data['update_available'], $data['release_url'], $data['notes'])
            : UpdateCheckResult::unavailable($data['reason']);
    }
}
