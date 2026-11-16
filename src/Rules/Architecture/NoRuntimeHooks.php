<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Architecture;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;

#[Rule(
    label: 'Runtime hooks:',
    advice: 'Register lifecycle and error handling once at an explicit application boundary.',
)]
final class NoRuntimeHooks extends Check
{
    /**
     * Report each matching PHP construct at its original source position.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        $nodes = (new NodeFinder)->find($file->nodes, fn (Node $node): bool => $node instanceof Expr\FuncCall && $node->name instanceof Name
                && in_array(strtolower($node->name->toString()), ['register_shutdown_function', 'set_error_handler'], true));

        foreach ($nodes as $node) {
            yield new Violation(
                rule: $this->id(),
                file: $file->path,
                value: 1,
                threshold: 0,
                line: $node->getStartLine(),
                message: 'Global runtime hooks create non-local behavior. Keep lifecycle and error handling at an explicit boundary.',
            );
        }
    }

    /**
     * Return the stable identifier used in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'no_runtime_hooks';
    }

    /**
     * This check uses the resolved AST directly and needs no extra metrics.
     *
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return [];
    }
}
