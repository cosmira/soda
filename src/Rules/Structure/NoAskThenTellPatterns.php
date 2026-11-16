<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;

#[Rule(
    label: 'Ask-then-tell patterns:',
    advice: 'Tell the object what outcome you want instead of querying its state and deciding for it.',
)]
final class NoAskThenTellPatterns extends Check
{
    /**
     * Return the stable identifier used in diagnostics.
     */
    #[\Override]
    public function id(): string
    {
        return 'max_ask_then_tell_patterns';
    }

    /**
     * Request query-then-command facts before this rule runs.
     *
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return ['askThenTell'];
    }

    /**
     * Report recorded query-then-command patterns on the same receiver at their original source locations.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        foreach ($file->metrics['askThenTell'] ?? [] as $index => $entry) {
            yield new Violation($this->id(), $file->path, $index + 1, 0, method: $entry['method'] ?? null, class: $entry['class'] ?? null,
                line: $entry['line'] ?? null,
                message: sprintf('Asked %s via %s(), then told it via %s()', $entry['receiver'] ?? 'object', $entry['question'] ?? 'query', $entry['command'] ?? 'command'),
            );
        }
    }
}
