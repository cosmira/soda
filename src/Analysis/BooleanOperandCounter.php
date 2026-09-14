<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BinaryOp\LogicalAnd;
use PhpParser\Node\Expr\BinaryOp\LogicalOr;
use PhpParser\Node\Expr\BinaryOp\LogicalXor;
use PhpParser\Node\Expr\BooleanNot;

/**
 * @internal
 */
final class BooleanOperandCounter
{
    /**
     * Count  in the supplied syntax.
     */
    public static function count(Expr $expr): int
    {
        return match (true) {
            $expr instanceof LogicalAnd,
            $expr instanceof LogicalOr,
            $expr instanceof LogicalXor,
            $expr instanceof BooleanAnd,
            $expr instanceof BooleanOr  => self::count($expr->left) + self::count($expr->right),
            $expr instanceof BooleanNot => 1,
            default                     => 1,
        };
    }
}
