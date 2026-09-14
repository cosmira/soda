<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;

/**
 * @internal
 */
final readonly class TellDontAskCommandCollector
{
    /**
     * Initialize the configured values and collaborators for this instance.
     */
    public function __construct(
        private TellDontAskReceiverFingerprint $fingerprint,
    ) {}

    /**
     * @return list<array{receiver: string, method: string}>
     */
    private function commandFromCall(MethodCall|NullsafeMethodCall|StaticCall $expr): array
    {
        $receiver = $this->fingerprint->forCall($expr);
        $method = $this->fingerprint->callName($expr);
        $isIncompleteCall = $receiver === null || $method === null;

        if ($isIncompleteCall) {
            return [];
        }

        return [[
            'receiver' => $receiver,
            'method'   => $method,
        ]];
    }

    /**
     * @return list<array{receiver: string, method: string}>
     */
    public function fromExpr(Expr $expr): array
    {
        $isCall = $expr instanceof MethodCall || $expr instanceof NullsafeMethodCall || $expr instanceof StaticCall;
        if ($isCall) {
            return $this->commandFromCall($expr);
        }

        if ($expr instanceof Ternary) {
            return [
                ...$this->fromExpr($expr->if ?? $expr->cond),
                ...$this->fromExpr($expr->else),
            ];
        }

        $isConjunction = $expr instanceof BinaryOp\BooleanAnd || $expr instanceof BinaryOp\LogicalAnd;

        if ($isConjunction) {
            return $this->fromExpr($expr->right);
        }

        return [];
    }

    /**
     * @param list<Node> $statements
     *
     * @return list<array{receiver: string, method: string}>
     */
    public function fromStatements(array $statements): array
    {
        $commands = [];

        foreach ($statements as $statement) {
            if ($statement instanceof Expression) {
                $commands = [...$commands, ...$this->fromExpr($statement->expr)];

                continue;
            }

            $isIf = $statement instanceof If_;

            if (! $isIf) {
                continue;
            }

            $commands = [...$commands, ...$this->fromStatements($statement->stmts)];

            foreach ($statement->elseifs as $elseif) {
                $commands = [...$commands, ...$this->fromStatements($elseif->stmts)];
            }

            $elseStatements = $statement->else?->stmts ?? [];

            if ($elseStatements !== []) {
                $commands = [...$commands, ...$this->fromStatements($elseStatements)];
            }
        }

        return $commands;
    }
}
