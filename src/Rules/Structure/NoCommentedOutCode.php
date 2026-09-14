<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;

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
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return ['comments'];
    }

    /**
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
