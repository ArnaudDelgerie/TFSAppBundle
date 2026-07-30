<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Bridge;

final class UpdateCheckResult
{
    /**
     * @param array{name: string, url: string, size: int}|null $asset
     */
    private function __construct(
        private readonly string $status,
        private readonly ?string $current,
        private readonly ?string $latest,
        private readonly ?bool $updateAvailable,
        private readonly ?string $releaseUrl,
        private readonly ?string $notes,
        private readonly ?array $asset,
        private readonly ?string $reason,
    ) {
    }

    /**
     * @param array{name: string, url: string, size: int} $asset
     */
    public static function ok(string $current, string $latest, bool $updateAvailable, string $releaseUrl, string $notes, array $asset): self
    {
        return new self('ok', $current, $latest, $updateAvailable, $releaseUrl, $notes, $asset, null);
    }

    public static function unavailable(string $reason): self
    {
        return new self('unavailable', null, null, null, null, null, null, $reason);
    }

    /**
     * True iff status is "ok" — the update group was reached and answered.
     */
    public function wasReached(): bool
    {
        return 'ok' === $this->status;
    }

    public function isUpdateAvailable(): bool
    {
        return $this->updateAvailable ?? false;
    }

    public function current(): ?string
    {
        return $this->current;
    }

    public function latest(): ?string
    {
        return $this->latest;
    }

    public function releaseUrl(): ?string
    {
        return $this->releaseUrl;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    /**
     * @return array{name: string, url: string, size: int}|null
     */
    public function asset(): ?array
    {
        return $this->asset;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }
}
