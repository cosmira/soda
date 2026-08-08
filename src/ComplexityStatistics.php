<?php

declare(strict_types=1);

namespace Bunnivo\Soda;

use SebastianBergmann\Complexity\ComplexityCollection;

final class ComplexityStatistics
{
    /**
     * @psalm-return array{minimum: int, maximum: int, average: float}
     */
    public static function from(ComplexityCollection $items): array
    {
        $values = collect($items)->map(fn ($item) => $item->cyclomaticComplexity());

        return [
            'minimum' => $values->min() ?? 0,
            'maximum' => $values->max() ?? 0,
            'average' => (float) ($values->avg() ?? 0.0),
        ];
    }
}
