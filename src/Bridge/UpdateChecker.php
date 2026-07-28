<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Bridge;

use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\UpdateNotEnabledException;

final class UpdateChecker implements UpdateCheckerInterface
{
    public function __construct(private readonly BridgeTransport $transport)
    {
    }

    public function isAvailable(): bool
    {
        return $this->transport->isConfigured();
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
         *     reason?: string,
         * } $data
         */
        $data = $response->toArray();

        return 'ok' === $data['status']
            ? UpdateCheckResult::ok($data['current'], $data['latest'], $data['update_available'], $data['release_url'])
            : UpdateCheckResult::unavailable($data['reason']);
    }
}
