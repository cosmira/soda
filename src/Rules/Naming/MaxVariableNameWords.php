<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Naming;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use InvalidArgumentException;

/**
 * Reports local variables and parameters whose names contain too many words.
 *
 * @example soda.php: `new MaxVariableNameWords(maxWords: 1, ignore: ['openAI'])`
 */
final class MaxVariableNameWords extends Check
{
    /**
     * @var array<string, true>
     */
    private array $ignored;

    /**
     * @param list<string> $ignore Exact variable names, with or without the leading `$`.
     */
    public function __construct(
        private readonly int $maxWords = 1,
        array $ignore = [],
    ) {
        throw_if($maxWords < 1, InvalidArgumentException::class, 'maxWords must be at least 1.');

        $ignored = [];
        foreach ($ignore as $name) {
            $ignored[ltrim($name, '$')] = true;
        }

        $this->ignored = $ignored;
    }

    /**
     * Identify this rule in reports.
     */
    public function id(): string
    {
        return 'max_variable_name_words';
    }

    /**
     * Use the already parsed syntax tree.
     *
     * @return list<string>
     */
    public function requiredAnalyses(): array
    {
        return ['naming'];
    }

    /**
     * Report compound names from the shared naming collector.
     *
     * @return iterable<Violation>
     */
    public function checkFile(FileFacts $file): iterable
    {
        foreach ($file->metrics['compoundVariableNames'] ?? [] as $occurrence) {
            if ($this->isViolation($occurrence)) {
                yield $this->violation($file->path, $occurrence);
            }
        }
    }

    /**
     * Determine whether violation applies to the supplied input.
     */
    private function isViolation(CompoundVariableNameOccurrence $occurrence): bool
    {
        return $occurrence->wordCount() > $this->maxWords
            && ! isset($this->ignored[$occurrence->name]);
    }

    /**
     * Construct a diagnostic with the applicable limits and source context.
     */
    private function violation(string $file, CompoundVariableNameOccurrence $occurrence): Violation
    {
        return new Violation(rule: 'max_variable_name_words', file: $file, value: $occurrence->wordCount(), threshold: $this->maxWords, method: $occurrence->method, class: $occurrence->class, line: $occurrence->line, message: sprintf(
            'Variable "$%s" contains %d words (%s). Prefer one domain concept; if qualifiers encode a unit, format, source, or role, introduce a value object.',
            $occurrence->name,
            $occurrence->wordCount(),
            implode(', ', $occurrence->words),
        ));
    }
}
