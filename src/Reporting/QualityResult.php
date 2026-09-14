<?php

declare(strict_types=1);

namespace Cosmira\Soda\Reporting;

use Illuminate\Support\Collection;

final readonly class QualityResult
{
    /**
     * @psalm-var Collection<int, Violation>
     */
    public Collection $violations;

    /**
     * @psalm-param Collection<int, Violation>|list<Violation> $violations
     */
    public function __construct(
        Collection|array $violations,
    ) {
        /** @var Collection<int, Violation> $col */
        $col = $violations instanceof Collection ? $violations : collect($violations);
        $this->violations = $col;
    }

    /**
     * Report whether analysis completed without any violations.
     */
    public function isPassing(): bool
    {
        return $this->violations->isEmpty();
    }
}
