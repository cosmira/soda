<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;

#[Rule(
    label: 'Commented-out code lines:',
    advice: 'Delete dead code and trust version control; preserve an explanation only when it documents why.',
)]
final class NoCommentedOutCode extends Check
{
    /**
     * Return the stable identifier used in diagnostics.
     */
    #[\Override]
    public function id(): string
    {
        return 'max_commented_out_code_lines';
    }

    /**
     * Request source comment facts before this rule runs.
     *
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return ['comments'];
    }

    /**
     * Report recorded code retained inside source comments at their original source locations.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        foreach ($file->metrics['commentedCode'] ?? [] as $index => $entry) {
            yield new Violation($this->id(), $file->path, $index + 1, 0,
                line: $entry['line'] ?? null,
                message: 'Commented-out code: '.($entry['text'] ?? ''),
            );
        }
    }
}
