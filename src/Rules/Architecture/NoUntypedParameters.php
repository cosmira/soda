<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Architecture;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Removing a parameter type must not bypass type-based restrictions.
 */
final class NoUntypedParameters extends Check
{
    /**
     * Require a native type on each parameter declaration.
     */
    public function checkFile(FileFacts $file): iterable
    {
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, Node\Param::class) as $parameter) {
            if ($parameter->type !== null) {
                continue;
            }

            $name = $parameter->var instanceof Node\Expr\Variable && is_string($parameter->var->name) ? $parameter->var->name : '?';
            yield new Violation(
                rule: $this->id(), file: $file->path, value: 1, threshold: 0,
                line: $parameter->getStartLine(),
                message: 'Parameter $'.$name.' must declare its accepted type.',
            );
        }
    }

    /**
     * Identify this check in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'no_untyped_parameters';
    }

    /**
     * Declare the fact collectors required by this check.
     */
    public function requiredAnalyses(): array
    {
        return [];
    }
}
