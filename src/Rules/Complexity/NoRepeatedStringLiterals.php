<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use InvalidArgumentException;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;

/**
 * Optionally reports repeated expression strings within one class-like declaration.
 */
#[Rule(
    label: 'Repeated expression string literals:',
    advice: 'Name a repeated protocol value when it has one meaning; literal array keys are excluded by default.',
    default: 3,
)]
final class NoRepeatedStringLiterals extends Check
{
    /**
     * Configure the triggering occurrence count, byte length and exact literal exceptions.
     *
     * @param list<string> $ignoredStrings Intentional repeated literal values.
     */
    public function __construct(
        /**
         * Minimum number of equal occurrences required for a finding.
         */
        private readonly int $minOccurrences = 3,
        /**
         * Minimum byte length of a non-whitespace literal to consider.
         */
        private readonly int $minLength = 3,
        /**
         * Exact decoded strings explicitly accepted by the project.
         */
        private readonly array $ignoredStrings = [],
        /**
         * Include literal array keys when enforcing the strict expression policy.
         */
        private readonly bool $includeArrayKeys = false,
    ) {
        throw_if($minOccurrences < 2, InvalidArgumentException::class, 'String occurrence threshold must be at least two.');
        throw_if($minLength < 1, InvalidArgumentException::class, 'Minimum string byte length must be positive.');
        foreach ($ignoredStrings as $value) {
            throw_if(! is_string($value), InvalidArgumentException::class, 'Ignored literal values must be strings.');
        }
    }

    /**
     * Identify the repeated-literal policy.
     */
    public function id(): string
    {
        return 'no_repeated_string_literals';
    }

    /**
     * Report one group per owner and value at the occurrence that crosses the permitted count.
     */
    public function checkFile(FileFacts $file): iterable
    {
        $collector = new ClassStringLiterals($this->includeArrayKeys);
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, ClassLike::class) as $class) {
            foreach ($collector->collect($class) as $group) {
                $lines = $group['lines'];
                $count = count($lines);
                if (! $this->isRepeated($group['value'], $count)) {
                    continue;
                }

                yield new Violation(
                    rule: $this->id(), file: $file->path, value: $count, threshold: $this->minOccurrences - 1,
                    class: $class->namespacedName?->toString() ?? '{anonymous-class@'.$class->getStartLine().'}',
                    line: $lines[$this->minOccurrences - 1],
                    message: 'Repeated string '.json_encode($group['value'], JSON_INVALID_UTF8_SUBSTITUTE).' at lines '.implode(', ', $lines)
                        .'. Name a shared protocol value when it has one meaning; configure intentional repetitions explicitly.',
                );
            }
        }
    }

    /**
     * Apply exact exceptions and documented count and byte-length boundaries.
     */
    private function isRepeated(string $value, int $count): bool
    {
        return $count >= $this->minOccurrences && strlen($value) >= $this->minLength
            && trim($value) !== '' && ! in_array($value, $this->ignoredStrings, true);
    }
}
