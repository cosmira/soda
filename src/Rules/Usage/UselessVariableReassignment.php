<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\NodeFinder;

/**
 * Recognises a copy followed by one conditional transformation of its source.
 */
final readonly class UselessVariableReassignment
{
    /**
     * Removing the copy must not discard a separately observed original value.
     *
     * @param list<Node> $before
     * @param list<Node> $after
     */
    public static function canInline(FunctionLike $scope, UselessVariableAliasAssignment $alias, array $before, array $after): bool
    {
        $finder = new NodeFinder;
        $priorUses = $finder->find($before, static fn (Node $node): bool => $node instanceof Expr\Variable && $node->name === $alias->variable);
        if ($priorUses !== [] || self::hasObservableBindings($scope, $alias)) {
            return false;
        }

        $mutations = $finder->find($after, static fn (Node $node): bool => UselessVariableMutation::isMutationOf($node, $alias->variable));
        if (count($mutations) !== 1) {
            return false;
        }

        $assignment = $mutations[0];
        if (! $assignment instanceof Expr\Assign || ! $assignment->var instanceof Expr\Variable) {
            return false;
        }

        $sourceReads = $finder->find($assignment->expr, static fn (Node $node): bool => $node instanceof Expr\Variable && $node->name === $alias->source);
        $allSourceReads = $finder->find($after, static fn (Node $node): bool => $node instanceof Expr\Variable && $node->name === $alias->source);

        return $sourceReads !== [] && $sourceReads === $allSourceReads && ! self::hasRepeatedExecution($after);
    }

    /**
     * Reject loops because each iteration must keep reading the original source.
     */
    private static function hasRepeatedExecution(array $nodes): bool
    {
        return (new NodeFinder)->find($nodes, static fn (Node $node): bool => in_array($node->getType(), [
            'Stmt_For', 'Stmt_Foreach', 'Stmt_While', 'Stmt_Do', 'Stmt_Goto', 'Stmt_Label',
        ], true)) !== [];
    }

    /**
     * References, shared state and lexical snapshots can expose the original value.
     */
    private static function hasObservableBindings(FunctionLike $scope, UselessVariableAliasAssignment $alias): bool
    {
        $names = [$alias->variable, $alias->source];
        $isRuntimeVariable = in_array($alias->source, ['this', 'GLOBALS', '_SERVER', '_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_REQUEST', '_ENV'], true);
        if ($scope->returnsByRef() || $isRuntimeVariable) {
            return true;
        }

        foreach ($scope->getParams() as $parameter) {
            if ($parameter->byRef && $parameter->var instanceof Expr\Variable && in_array($parameter->var->name, $names, true)) {
                return true;
            }
        }

        return (new NodeFinder)->find($scope->getStmts() ?? [], static fn (Node $node): bool => self::isObservableBinding($node, $names)) !== [];
    }

    /**
     * Keep the check conservative where local variable identity is observable.
     *
     * @param list<string> $names
     */
    private static function isObservableBinding(Node $node, array $names): bool
    {
        $type = $node->getType();
        if (in_array($type, ['Expr_AssignRef', 'Stmt_Global', 'Stmt_Static', 'Expr_Eval', 'ClosureUse'], true)) {
            return true;
        }

        if ($node instanceof Expr\Variable && ! is_string($node->name)) {
            return true;
        }

        if ($node instanceof Expr\ArrowFunction) {
            return (new NodeFinder)->find($node->expr, static fn (Node $child): bool => $child instanceof Expr\Variable && in_array($child->name, $names, true)) !== [];
        }

        return self::isSymbolTableCall($node) || ($node instanceof Node\Arg && $node->byRef)
            || ($node instanceof Node\Stmt\Foreach_ && $node->byRef);
    }

    /**
     * Calls that access locals by name prevent safe replacement of the source variable.
     */
    private static function isSymbolTableCall(Node $node): bool
    {
        return $node instanceof Expr\FuncCall && (! $node->name instanceof Node\Name
            || in_array(strtolower($node->name->toString()), ['compact', 'get_defined_vars', 'extract'], true));
    }
}
