<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Tracks reads of an incoming parameter before its value is definitely overwritten.
 */
final readonly class ParameterUseAnalysis
{
    /**
     * Retain the declaration only for the duration of its lexical callable analysis.
     */
    public function __construct(private Node\Param $parameter) {}

    /**
     * A by-reference output write is useful even without reading the incoming value.
     */
    public function isUsed(Node\FunctionLike $callable): bool
    {
        $state = $this->scan($callable->getStmts() ?? [], ['alive' => true, 'used' => false, 'continues' => true]);

        return $state['used'];
    }

    /**
     * Distinguish value reads, writes, nested scopes, and branch exits.
     */
    private function node(Node $node, array $state): array
    {
        if ($node instanceof Stmt\ClassLike || $node instanceof Stmt\Function_) {
            return $state;
        }

        if ($node instanceof Stmt\Expression) {
            return $node->expr instanceof Expr\Variable ? $state : $this->expression($node->expr, $state);
        }

        if ($node instanceof Stmt\If_) {
            return $this->branches($node, $state);
        }

        return $node instanceof Expr ? $this->expression($node, $state) : $this->children($node, $state);
    }

    /**
     * Model expression reads and writes separately from statement-level branch exits.
     */
    private function expression(Expr $node, array $state): array
    {
        return match (true) {
            $node instanceof Node\FunctionLike                                                                                         => $this->capture($node, $state),
            $node instanceof Expr\Ternary                                                                                              => $this->ternary($node, $state),
            $node instanceof Expr\BinaryOp                                                                                             => $this->binary($node, $state),
            $node instanceof Expr\Assign, $node instanceof Expr\AssignRef                                                              => $this->assignment($node, $state),
            ParameterRead::isScopeSnapshot($node, $this->parameter), ParameterRead::isVariable($node, $this->parameter)                => array_replace($state, ['used' => $state['alive']]),
            default                                                                                                                    => $this->children($node, $state),
        };
    }

    /**
     * All paths must overwrite the parameter to make a later read independent of its input.
     */
    private function branches(Stmt\If_ $node, array $state): array
    {
        $remaining = $this->scan([$node->cond], $state);
        $paths = [$this->scan($node->stmts, $remaining)];
        foreach ($node->elseifs as $branch) {
            $remaining = $this->scan([$branch->cond], $remaining);
            $paths[] = $this->scan($branch->stmts, $remaining);
        }

        $paths[] = $this->scan($node->else?->stmts ?? [], $remaining);

        return $this->join($paths);
    }

    /**
     * Closures explicitly capture values; arrow parameters shadow implicit capture.
     */
    private function capture(Node\FunctionLike $node, array $state): array
    {
        if ($node instanceof Expr\Closure) {
            return $this->closure($node, $state);
        }

        if ($this->isShadowed($node)) {
            return $state;
        }

        $captured = $this->scan($node->getStmts() ?? [], $state);

        return array_replace($state, ['used' => $captured['used']]);
    }

    /**
     * The selected ternary arm can overwrite the input only if both paths do so.
     */
    private function ternary(Expr\Ternary $node, array $state): array
    {
        $state = $this->scan([$node->cond], $state);

        return $this->join([$this->scan([$node->if], $state), $this->scan([$node->else], $state)]);
    }

    /**
     * A short-circuit operator can skip its right operand and preserve the incoming value.
     */
    private function binary(Expr\BinaryOp $node, array $state): array
    {
        $isConditional = in_array($node->getType(), [
            'Expr_BinaryOp_BooleanAnd', 'Expr_BinaryOp_BooleanOr',
            'Expr_BinaryOp_LogicalAnd', 'Expr_BinaryOp_LogicalOr', 'Expr_BinaryOp_Coalesce',
        ], true);
        if (! $isConditional) {
            return $this->children($node, $state);
        }

        return $this->shortCircuit($node, $state);
    }

    /**
     * Evaluate the right-hand value before replacing a parameter; reference writes are useful output.
     */
    private function assignment(Expr\Assign|Expr\AssignRef $node, array $state): array
    {
        $state = $this->scan([$node->expr], $state);
        if (! ParameterRead::isVariable($node->var, $this->parameter)) {
            return $this->scan([$node->var], $state);
        }

        $state['used'] = $state['used'] || $this->parameter->byRef;
        $state['alive'] = false;

        return $state;
    }

    /**
     * Explicit closure capture reads an incoming value at closure creation.
     */
    private function closure(Expr\Closure $node, array $state): array
    {
        foreach ($node->uses as $use) {
            if (ParameterRead::isVariable($use->var, $this->parameter)) {
                $state['used'] = $state['alive'];
            }
        }

        return $state;
    }

    /**
     * A named arrow parameter shadows the outer input instead of capturing it.
     */
    private function isShadowed(Node\FunctionLike $node): bool
    {
        foreach ($node->getParams() as $parameter) {
            if (ParameterRead::isVariable($parameter->var, $this->parameter)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Process straight-line statements in order and join conditional paths conservatively.
     */
    private function scan(array $nodes, array $state): array
    {
        foreach ($nodes as $node) {
            if (! $node instanceof Node || ! $state['continues'] || $state['used']) {
                continue;
            }

            $state = $this->node($node, $state);
        }

        return $state;
    }

    /**
     * Retain input values surviving any continuing path and reads from every path.
     */
    private function join(array $paths): array
    {
        $result = ['alive' => false, 'used' => false, 'continues' => false];
        foreach ($paths as $path) {
            $result['used'] = $result['used'] || $path['used'];
            $result['alive'] = $result['alive'] || ($path['alive'] && $path['continues']);
            $result['continues'] = $result['continues'] || $path['continues'];
        }

        return $result;
    }

    /**
     * Preserve input on paths that may bypass a loop, switch case or exceptional write.
     */
    private function children(Node $node, array $state): array
    {
        $result = $state;
        foreach ($node->getSubNodeNames() as $field) {
            $child = get_object_vars($node)[$field];
            $result = $this->scan($child instanceof Node ? [$child] : (is_array($child) ? $child : []), $result);
        }

        $isExit = in_array($node->getType(), ['Stmt_Return', 'Expr_Throw'], true);
        if ($isExit) {
            $result['continues'] = false;
        }

        $canBypass = in_array($node->getType(), ['Stmt_While', 'Stmt_For', 'Stmt_Foreach', 'Stmt_Switch', 'Stmt_TryCatch'], true);
        if ($canBypass) {
            $result['alive'] = $result['alive'] || $state['alive'];
            $result['continues'] = $state['continues'];
        }

        return $result;
    }

    /**
     * Join the evaluated right operand with the path that skips it.
     */
    private function shortCircuit(Expr\BinaryOp $node, array $state): array
    {
        $state = $this->scan([$node->left], $state);

        return $this->join([$state, $this->scan([$node->right], $state)]);
    }
}
