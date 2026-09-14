<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\ExpressionCheck;
use InvalidArgumentException;

/**
 * Optional threshold for Soda's documented PHP cognitive variant, with per-point evidence.
 */
final class MaxCognitiveComplexity extends ExpressionCheck
{
    /**
     * Zero permits only linear code; negative limits are invalid for this new check.
     */
    public function __construct(private readonly int $limit = 15)
    {
        throw_if($limit < 0, InvalidArgumentException::class, 'Cognitive complexity limit must be non-negative.');
    }

    /**
     * Identify the optional threshold in configuration and reports.
     */
    #[\Override]
    public function id(): string
    {
        return 'max_cognitive_complexity';
    }

    /**
     * Keep the comparison next to its validated constructor parameter.
     */
    #[\Override]
    protected function expression(): string
    {
        return sprintf('method where cognitive_complexity > %d', $this->limit);
    }

    /**
     * Include anonymous scopes without adding them to other rules' legacy method surface.
     *
     * @return iterable<array<string, mixed>>
     */
    #[\Override]
    protected function rows(FileFacts $file): iterable
    {
        yield from parent::rows($file);
        yield from $file->metrics['cognitiveExtras'] ?? [];
    }

    /**
     * Report the real score and every contributing line rather than an opaque severity.
     *
     * @param array<string, mixed> $row
     */
    #[\Override]
    protected function violation(FileFacts $file, array $row): Violation
    {
        $contributions = array_map(
            fn (array $point): string => sprintf('line %d +%d %s', $point['line'], $point['increment'], $point['reason']),
            $row['cognitive_contributions'],
        );

        return new Violation(
            rule: $this->id(), file: $file->path,
            value: $row['cognitive_complexity'], threshold: $this->limit,
            method: $row['name'], line: $row['line'],
            message: 'Soda PHP cognitive v1: '.implode('; ', $contributions).'. Review the control flow before extracting objects.',
        );
    }
}
