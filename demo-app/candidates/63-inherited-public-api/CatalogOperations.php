<?php

declare(strict_types=1);

namespace DemoApp\Catalog;

abstract class CatalogOperations
{
    public function import(): string
    {
        return 'import';
    }

    public function export(): string
    {
        return 'export';
    }

    public function publish(): string
    {
        return 'publish';
    }

    public function unpublish(): string
    {
        return 'unpublish';
    }

    public function index(): string
    {
        return 'index';
    }

    public function search(): string
    {
        return 'search';
    }

    public function filter(): string
    {
        return 'filter';
    }

    public function classify(): string
    {
        return 'classify';
    }

    public function price(): string
    {
        return 'price';
    }

    public function discount(): string
    {
        return 'discount';
    }

    public function localize(): string
    {
        return 'localize';
    }

    public function validate(): string
    {
        return 'validate';
    }

    public function enrich(): string
    {
        return 'enrich';
    }

    public function deduplicate(): string
    {
        return 'deduplicate';
    }

    public function synchronize(): string
    {
        return 'synchronize';
    }

    public function snapshot(): string
    {
        return 'snapshot';
    }

    public function restore(): string
    {
        return 'restore';
    }

    public function archive(): string
    {
        return 'archive';
    }
}
