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
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

final class NoDynamicInvocation extends Check
{
    /**
     * Report each matching PHP construct at its original source position.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        $nodes = (new NodeFinder)->find($file->nodes, fn (Node $node): bool => ($node instanceof Expr\FuncCall && $node->name instanceof Name
                && in_array(strtolower($node->name->toString()), ['call_user_func', 'call_user_func_array', 'forward_static_call', 'forward_static_call_array'], true))
                || ($node instanceof Expr\FuncCall && ! $node->name instanceof Name)
                || $this->isDynamicConstruction($node) || $this->isDynamicCall($node));

        foreach ($nodes as $node) {
            yield new Violation(
                rule: $this->id(),
                file: $file->path,
                value: 1,
                threshold: 0,
                line: $node->getStartLine(),
                message: 'Dynamic invocation hides the callable contract. Use an explicit interface or dispatch map.',
            );
        }
    }

    /**
     * Determine whether dynamic construction applies to the supplied input.
     */
    private function isDynamicConstruction(Node $node): bool
    {
        return $node instanceof Expr\New_ && ! $node->class instanceof Name && ! $node->class instanceof Stmt\Class_;
    }

    /**
     * Recognize method names selected at runtime instead of declared identifiers.
     */
    private function isDynamicCall(Node $node): bool
    {
        return ($node instanceof Expr\MethodCall && ! $node->name instanceof Identifier)
            || ($node instanceof Expr\StaticCall && ! $node->name instanceof Identifier);
    }

    /**
     * Return the stable identifier used in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'no_dynamic_invocation';
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
