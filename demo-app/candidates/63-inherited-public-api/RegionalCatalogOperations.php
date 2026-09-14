<?php

declare(strict_types=1);

namespace DemoApp\Catalog;

final class RegionalCatalogOperations extends CatalogOperations
{
    public function embargo(): string
    {
        return 'embargo';
    }

    public function allocate(): string
    {
        return 'allocate';
    }

    public function reconcile(): string
    {
        return 'reconcile';
    }
}
