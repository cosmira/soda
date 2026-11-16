<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;

#[Rule(
    label: 'Empty catch blocks:',
    advice: 'Handle the failure, translate it to a domain outcome, or document explicitly why ignoring it is safe.',
)]
final class NoEmptyCatchBlocks extends Check
{
    /**
     * Return the stable identifier used in diagnostics.
     */
    #[\Override]
    public function id(): string
    {
        return 'max_empty_catch_blocks';
    }

    /**
     * Request empty catch locations before this rule runs.
     *
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return ['emptyCatches'];
    }

    /**
     * Report recorded catch blocks that silently discard exceptions at their original source locations.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        foreach ($file->metrics['emptyCatches'] ?? [] as $index => $entry) {
            yield new Violation($this->id(), $file->path, $index + 1, 0, method: $entry['method'] ?? null, class: $entry['class'] ?? null,
                line: $entry['line'] ?? null,
                message: 'Empty catch block',
            );
        }
    }
}
