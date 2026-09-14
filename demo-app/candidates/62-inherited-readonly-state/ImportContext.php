<?php

declare(strict_types=1);

namespace DemoApp\Catalog;

abstract readonly class ImportContext
{
    public function __construct(
        public string $batchId,
        public string $fileName,
        public string $requestedBy,
    ) {}
}
