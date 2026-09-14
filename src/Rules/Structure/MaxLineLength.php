<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;

final class MaxLineLength extends Check
{
    /**
     * Set the maximum accepted value; non-positive limits preserve the disabled policy.
     */
    public function __construct(private readonly int $limit) {}

    /**
     * Return the stable identifier used in diagnostics.
     */
    #[\Override]
    public function id(): string
    {
        return 'max_line_length';
    }

    /**
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Count bytes on physical source lines without reading the file again.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        $lines = explode("\n", $file->source);
        if (str_ends_with($file->source, "\n")) {
            array_pop($lines);
        }

        foreach ($lines as $number => $line) {
            $length = strlen(rtrim($line, "\r"));
            if ($length > $this->limit) {
                yield new Violation($this->id(), $file->path, $length, $this->limit, line: $number + 1);
            }
        }
    }
}
