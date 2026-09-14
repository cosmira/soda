<?php

declare(strict_types=1);

final class ArchivePolicy
{
    public function __construct(private readonly bool $retained) {}

    public function isRetained(): bool
    {
        return $this->retained;
    }
}

$policy = new ArchivePolicy(retained: true);

echo json_encode(['retained' => $policy->isRetained()], JSON_THROW_ON_ERROR).PHP_EOL;
