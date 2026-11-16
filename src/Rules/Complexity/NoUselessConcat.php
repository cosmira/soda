<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;

#[Rule(
    label: 'Unneeded literal concatenations:',
    advice: 'Combine same-line string literals while preserving bytes and escaping; keep multiline text layout.',
)]
final class NoUselessConcat extends Check
{
    /**
     * Preserve multiline text layout and expressions requiring evaluation or conversion.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, Concat::class) as $concat) {
            if (! $concat->left instanceof String_ || ! $concat->right instanceof String_) {
                continue;
            }

            if ($concat->getStartLine() !== $concat->getEndLine()) {
                continue;
            }

            yield new Violation(
                rule: $this->id(), file: $file->path, value: 1, threshold: 0,
                line: $concat->getStartLine(),
                message: 'Combine these string literals into one literal, preserving their bytes and escaping.',
            );
        }
    }

    /**
     * Return the stable identifier used in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'no_useless_concat';
    }
}
