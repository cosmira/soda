<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter\Standard;

/**
 * Reports fallback assignments that repeat the target on both sides.
 */
#[Rule(
    label: 'Repeated null-coalesce targets:',
    advice: 'Assign the fallback with the ??= operator; targets evaluated through calls stay outside the comparison.',
)]
final class PreferNullCoalesceAssignment extends Check
{
    /**
     * Parser links delimit the local binding used by a dimension target.
     */
    private const PARENT = 'parent';

    /**
     * Return the stable rule identifier.
     */
    #[\Override]
    public function id(): string
    {
        return 'prefer_null_coalesce_assignment';
    }

    /**
     * Report `$target = $target ?? fallback` patterns.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $facts): iterable
    {
        $printer = new Standard;
        foreach ((new NodeFinder)->findInstanceOf($facts->nodes, Assign::class) as $assign) {
            if (! $assign->expr instanceof Coalesce) {
                continue;
            }

            $target = $printer->prettyPrintExpr($assign->var);
            if ($target !== $printer->prettyPrintExpr($assign->expr->left)) {
                continue;
            }

            if (StateWriteSearch::hasStateWriteOrCall($assign->var)) {
                continue;
            }

            if ($assign->var instanceof Expr\ArrayDimFetch && ! $this->isNativeArray($assign)) {
                continue;
            }

            yield new Violation(
                rule: $this->id(),
                file: $facts->path,
                value: 1,
                threshold: 0,
                line: $assign->getStartLine(),
                message: sprintf('Assign the fallback with the ??= operator instead of repeating %s on both sides.', $target),
            );
        }
    }

    /**
     * A native array container says nothing about the type of a nested element.
     */
    private function isNativeArray(Assign $assignment): bool
    {
        $target = $assignment->var;

        return $target instanceof Expr\ArrayDimFetch && $target->var instanceof Expr\Variable
            && is_string($target->var->name) && $this->hasArrayBinding($assignment, $target->var->name);
    }

    /**
     * Only an incoming native array is established before any statement can expose its binding.
     */
    private function hasArrayBinding(Assign $assignment, string $name): bool
    {
        $statement = $assignment->getAttribute(self::PARENT);
        if (! $statement instanceof Node\Stmt\Expression) {
            return false;
        }

        $scope = $statement->getAttribute(self::PARENT);
        if (! $scope instanceof FunctionLike) {
            return false;
        }

        return ($scope->getStmts()[0] ?? null) === $statement && $this->isArrayInput($name, $scope);
    }

    /**
     * Entry proof cannot be borrowed from a reference input or another lexical callable.
     */
    private function isArrayInput(string $name, FunctionLike $scope): bool
    {
        foreach ($scope->getParams() as $parameter) {
            if ($parameter->var instanceof Expr\Variable && $parameter->var->name === $name) {
                return ! $parameter->byRef && $parameter->type instanceof Identifier && $parameter->type->toString() === 'array';
            }
        }

        return false;
    }
}
