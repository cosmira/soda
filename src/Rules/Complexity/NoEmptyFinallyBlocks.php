<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Reports finally blocks with no executable statements, including comment-only blocks.
 */
final class NoEmptyFinallyBlocks extends Check
{
    /**
     * Identify this optional check in configuration and reports.
     */
    public function id(): string
    {
        return 'no_empty_finally_blocks';
    }

    /**
     * Reuse the parsed syntax tree without requesting additional measurements.
     *
     * @return list<string>
     */
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Explain both syntactic cases without emitting an unsafe automatic edit.
     *
     * @return iterable<Violation>
     */
    public function checkFile(FileFacts $file): iterable
    {
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, Stmt\TryCatch::class) as $try) {
            $finally = $try->finally;
            $isEmpty = $finally !== null
                && array_filter($finally->stmts, fn (Node $node): bool => ! $node instanceof Stmt\Nop) === [];
            if (! $isEmpty) {
                continue;
            }

            $message = $try->catches === []
                ? 'Empty finally: there is no cleanup. Without a catch, simplifying also requires unwrapping the try body; removing only finally is invalid PHP.'
                : 'Empty finally: there is no cleanup. Remove the empty block if it has no useful explanation to preserve.';
            yield new Violation(
                rule: $this->id(), file: $file->path, value: 1, threshold: 0,
                line: $finally->getStartLine(), message: $message,
            );
        }
    }
}
