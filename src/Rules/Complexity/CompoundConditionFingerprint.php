<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use PhpParser\Node;
use PhpParser\Node\Expr;

/** Encodes only the closed expression vocabulary supported by repetition checks. */
final class CompoundConditionFingerprint
{
    /**
     * Preserve structural and literal identity while ignoring formatting.
     */
    public function encode(Expr $expression, array $properties): ?string
    {
        return match (true) {
            $expression instanceof Expr\PropertyFetch => $this->property($expression, $properties),
            $expression instanceof Node\Scalar\String_,
            $expression instanceof Node\Scalar\Int_,
            $expression instanceof Node\Scalar\Float_ => serialize([$expression->getType(), $expression->value]),
            $expression instanceof Expr\ConstFetch    => $this->literal($expression),
            $expression instanceof Expr\BooleanNot,
            $expression instanceof Expr\UnaryMinus,
            $expression instanceof Expr\UnaryPlus => $this->unary($expression, $properties),
            $expression instanceof Expr\BinaryOp  => $this->binary($expression, $properties),
            default                               => null,
        };
    }

    /**
     * Require a direct read of a declared instance property.
     */
    private function property(Expr\PropertyFetch $expression, array $properties): ?string
    {
        $isOwner = $expression->var instanceof Expr\Variable && $expression->var->name === 'this';
        if (! $isOwner || ! $expression->name instanceof Node\Identifier) {
            return null;
        }

        $name = $expression->name->toString();

        return isset($properties[$name]) ? serialize(['property', $name]) : null;
    }

    /**
     * Recognize language literals independently of namespace resolution.
     */
    private function literal(Expr\ConstFetch $expression): ?string
    {
        $name = strtolower($expression->name->toString());

        return in_array($name, ['true', 'false', 'null'], true) ? serialize(['literal', $name]) : null;
    }

    /**
     * Allow logical negation and signed numeric literals only.
     */
    private function unary(Expr\BooleanNot|Expr\UnaryMinus|Expr\UnaryPlus $expression, array $properties): ?string
    {
        $isNumber = $expression->expr instanceof Node\Scalar\Int_ || $expression->expr instanceof Node\Scalar\Float_;
        if (! $expression instanceof Expr\BooleanNot && ! $isNumber) {
            return null;
        }

        $operand = $this->encode($expression->expr, $properties);

        return $operand === null ? null : serialize([$expression->getType(), $operand]);
    }

    /**
     * Keep only boolean combinations and strict comparisons, preserving operand order.
     */
    private function binary(Expr\BinaryOp $expression, array $properties): ?string
    {
        $supported = in_array($expression->getType(), [
            'Expr_BinaryOp_BooleanAnd', 'Expr_BinaryOp_BooleanOr',
            'Expr_BinaryOp_Identical', 'Expr_BinaryOp_NotIdentical',
        ], true);
        if (! $supported) {
            return null;
        }

        $left = $this->encode($expression->left, $properties);
        $right = $this->encode($expression->right, $properties);

        return $left === null || $right === null ? null : serialize([$expression->getType(), $left, $right]);
    }

    /**
     * Ensure a fingerprint represents a compound boolean decision.
     */
    public function hasBooleanOperator(Expr $expression): bool
    {
        $isBoolean = in_array($expression->getType(), ['Expr_BinaryOp_BooleanAnd', 'Expr_BinaryOp_BooleanOr'], true);
        if ($isBoolean) {
            return true;
        }

        if ($expression instanceof Expr\BooleanNot) {
            return $this->hasBooleanOperator($expression->expr);
        }

        if ($expression instanceof Expr\BinaryOp) {
            return $this->hasBooleanOperator($expression->left) || $this->hasBooleanOperator($expression->right);
        }

        return false;
    }
}
