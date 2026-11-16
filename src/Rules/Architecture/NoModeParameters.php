<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Architecture;

use Cosmira\Soda\Analysis\Control\ParameterControlAnalysis;
use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Rejects input selectors that dispatch multiple distinct operations.
 */
#[Rule(
    label: 'Mode parameters:',
    advice: 'Expose separate operations instead of selecting distinct operations with an input mode.',
    severity: 'error',
)]
final class NoModeParameters extends Check
{
    /**
     * Report one finding per parameter and include every observed dispatch location.
     */
    public function checkFile(FileFacts $file): iterable
    {
        $callables = (new NodeFinder)->find($file->nodes, fn (Node $node): bool => $node instanceof Node\FunctionLike);
        foreach ($callables as $callable) {
            if (! $callable instanceof Node\FunctionLike) {
                continue;
            }

            foreach ((new ParameterControlAnalysis)->analyze($callable)['modes'] as $parameter => $lines) {
                yield new Violation(
                    rule: $this->id(), file: $file->path, value: 1, threshold: 0,
                    line: min($lines),
                    message: sprintf('Parameter $%s selects distinct operations at lines %s. Expose separate named operations instead of a mode selector.', $parameter, implode(', ', array_unique($lines))),
                );
            }
        }
    }

    /**
     * Return the stable rule identifier.
     */
    public function id(): string
    {
        return 'no_mode_parameters';
    }
}
