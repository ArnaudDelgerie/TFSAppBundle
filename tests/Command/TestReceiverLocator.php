<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Command;

use Symfony\Contracts\Service\ServiceCollectionInterface;

final class TestReceiverLocator implements ServiceCollectionInterface
{
    /**
     * @param list<string> $transports
     */
    public function __construct(private readonly array $transports)
    {
    }

    public function get(string $id): mixed
    {
        throw new \LogicException('Test receiver locator is only inspected, never resolved.');
    }

    public function has(string $id): bool
    {
        return \in_array($id, $this->transports, true);
    }

    public function getProvidedServices(): array
    {
        return array_fill_keys($this->transports, '?');
    }

    public function count(): int
    {
        return count($this->transports);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator();
    }
}
