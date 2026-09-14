<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

final class FactCatalog
{
    /**
     * Describe every supported expression field from the same schema used by the compiler.
     *
     * @return array<string, array<string, array{type: string, unit: string, description: string, source: string, analysis: ?string}>>
     */
    public static function all(): array
    {
        static $facts = null;

        return $facts ??= require __DIR__.'/../../resources/facts.php';
    }
}
