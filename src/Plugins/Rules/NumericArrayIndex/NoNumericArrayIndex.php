<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\NumericArrayIndex;

use Bunnivo\Soda\Config\SodaRule;
use Bunnivo\Soda\Quality\Limits;
use Bunnivo\Soda\Quality\Report\ViolationBuilder;

/**
 * Forbids numeric literal array indices (`$a[0]`, `$a[-1]`, etc.).
 *
 * @example soda.php: `new NoNumericArrayIndex()`
 */
final class NoNumericArrayIndex extends SodaRule
{
    private const string MESSAGE = 'Numeric array index access is forbidden. Use named keys instead.';

    #[\Override]
    public function id(): string
    {
        return 'no_numeric_array_index';
    }

    #[\Override]
    protected function analyze(string $file): array
    {
        return [
            'numeric_array_index_hits' => (new NumericArrayIndexAnalyser)->analyse($this->parse($file)),
        ];
    }

    /**
     * @param array<string, mixed> $metrics
     */
    #[\Override]
    protected function evaluate(string $file, array $metrics): array
    {
        $violations = [];

        foreach ($metrics['numeric_array_index_hits'] ?? [] as $hit) {
            if (! is_array($hit)) {
                continue;
            }

            if (! isset($hit['line'])) {
                continue;
            }

            if (! is_int($hit['line'])) {
                continue;
            }

            $violations[] = ViolationBuilder::of(
                $this->id(),
                $file,
                new Limits(1, 0),
            )
                ->atLine($hit['line'])
                ->withMessage(self::MESSAGE)
                ->build();
        }

        return $violations;
    }
}
