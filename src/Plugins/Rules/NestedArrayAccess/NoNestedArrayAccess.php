<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\NestedArrayAccess;

use Bunnivo\Soda\Config\SodaRule;
use Bunnivo\Soda\Quality\Limits;
use Bunnivo\Soda\Quality\Report\ViolationBuilder;

/**
 * Forbids chained array access beyond a single level (e.g. `$a['b']['c']`).
 *
 * @example soda.php: `new NoNestedArrayAccess()` or `new NoNestedArrayAccess(maxDepth: 1)`
 */
final class NoNestedArrayAccess extends SodaRule
{
    private const string MESSAGE = 'Nested array access is forbidden. Use flat structures or DTO/objects instead.';

    public function __construct(
        private readonly int $maxDepth = 1,
    ) {}

    #[\Override]
    public function id(): string
    {
        return 'no_nested_array_access';
    }

    #[\Override]
    protected function analyze(string $file): array
    {
        return [
            'nested_array_access_hits' => (new NestedArrayAccessAnalyser($this->maxDepth))->analyse($this->parse($file)),
        ];
    }

    /**
     * @param array<string, mixed> $metrics
     */
    #[\Override]
    protected function evaluate(string $file, array $metrics): array
    {
        $violations = [];

        foreach ($metrics['nested_array_access_hits'] ?? [] as $hit) {
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
