<?php

declare(strict_types=1);

final readonly class FeatureState
{
    public function __construct(private ?bool $enabled) {}

    public function isInherited(): bool
    {
        return $this->enabled === null;
    }
}

$state = new FeatureState(enabled: null);

echo json_encode(['inherited' => $state->isInherited()], JSON_THROW_ON_ERROR).PHP_EOL;
