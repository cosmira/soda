<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Architecture;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;

final class NoClosureRebinding extends Check
{
    /**
     * Report each matching PHP construct at its original source position.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        $nodes = (new NodeFinder)->find($file->nodes, fn (Node $node): bool => $this->isClosureRebinding($node));

        foreach ($nodes as $node) {
            yield new Violation(
                rule: $this->id(),
                file: $file->path,
                value: 1,
                threshold: 0,
                line: $node->getStartLine(),
                message: 'Closure rebinding bypasses visible scope and ownership. Use an explicit collaborator contract.',
            );
        }
    }

    /**
     * Determine whether closure rebinding applies to the supplied input.
     */
    private function isClosureRebinding(Node $node): bool
    {
        $staticBind = $node instanceof Expr\StaticCall && $node->class instanceof Name && strtolower($node->class->getLast()) === 'closure' && $node->name instanceof Identifier && strtolower($node->name->name) === 'bind';
        $instanceBind = $node instanceof Expr\MethodCall && $node->name instanceof Identifier && strtolower($node->name->name) === 'bindto';

        return $staticBind || $instanceBind;
    }

    /**
     * Return the stable identifier used in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'no_closure_rebinding';
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
