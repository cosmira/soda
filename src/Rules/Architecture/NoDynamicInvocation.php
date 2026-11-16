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
    label: 'Dynamic invocations:',
    advice: 'Replace runtime-selected callables with an explicit interface or named dispatch map.',
)]
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
                || $this->isDynamicCall($node));

        foreach ($nodes as $node) {
            yield new Violation(
                rule: $this->id(),
                file: $file->path,
                value: 1,
                threshold: 0,
                line: $node->getStartLine(),
                message: $this->message($node),
            );
        }
    }

    /**
     * Native string magic constants retain their declaration context through trait aliases and closures.
     */
    private function isDynamicCall(Node $node): bool
    {
        return ($node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall || $node instanceof Expr\StaticCall)
            && ! $node->name instanceof Node\Identifier
            && ! $node->name instanceof Node\Scalar\String_
            && (! $node->name instanceof Node\Scalar\MagicConst || $node->name->getName() === '__LINE__');
    }

    /**
     * Describe the prohibited mechanism without claiming that every callback lacks a contract.
     */
    private function message(Node $node): string
    {
        if ($node instanceof Expr\FuncCall && $node->name instanceof Name) {
            return 'Indirect callable helpers are forbidden by this policy. Invoke the callable directly.';
        }

        return 'Computed method names are forbidden by this policy. Use a literal method name or explicit dispatch.';
    }

    /**
     * Return the stable identifier used in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'no_dynamic_invocation';
    }
}
