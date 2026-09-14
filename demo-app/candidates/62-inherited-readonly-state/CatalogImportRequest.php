<?php

declare(strict_types=1);

namespace DemoApp\Catalog;

final readonly class CatalogImportRequest extends ImportContext
{
    public function __construct(
        public string $catalog,
        public string $mode,
        public string $region,
    ) {
        parent::__construct('batch-62', 'catalog.csv', 'operator-4');
    }

    public function summary(): string
    {
        return $this->batchId.'|'.$this->catalog.'|'.$this->mode.'|'.$this->region;
    }
}
