<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use function array_key_exists;
use function array_reverse;
use function is_string;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;

/**
 * @internal
 */
final readonly class TellDontAskQuestionProbe
{
    /**
     * Initialize the configured values and collaborators for this instance.
     */
    public function __construct(
        private TellDontAskReceiverFingerprint $fingerprint,
    ) {}

    /**
     * Determine whether named variable applies to the supplied input.
     */
    private function isNamedVariable(Expr $expr): bool
    {
        return $expr instanceof Variable && is_string($expr->name);
    }

    /**
     * @param list<array<string, list<array{receiver: string, method: string, result?: string}>>> $questionAliases
     *
     * @return list<array{receiver: string, method: string, result?: string}>
     */
    private function aliasedQuestions(string $variable, array $questionAliases): array
    {
        foreach (array_reverse($questionAliases, true) as $aliases) {
            if (array_key_exists($variable, $aliases)) {
                return array_map(
                    static fn (array $question): array => [...$question, 'result' => $variable],
                    $aliases[$variable],
                );
            }
        }

        return [];
    }

    /**
     * Determine whether call expression type applies to the supplied input.
     */
    private function isCallExpressionType(string $exprType): bool
    {
        return in_array($exprType, ['Expr_MethodCall', 'Expr_NullsafeMethodCall', 'Expr_StaticCall'], true);
    }

    /**
     * @return list<array{receiver: string, method: string, result?: string}>
     */
    private function questionFromCall(MethodCall|NullsafeMethodCall|StaticCall $expr): array
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
     * @param list<array<string, list<array{receiver: string, method: string, result?: string}>>> $questionAliases
     *
     * @return list<array{receiver: string, method: string, result?: string}>
     */
    public function questions(Expr $expr, array $questionAliases): array
    {
        $questions = [];
        $exprType = $expr->getType();
        $isNamedVariable = $exprType === 'Expr_Variable' && $this->isNamedVariable($expr);

        if ($isNamedVariable) {
            /** @var Variable $expr */
            $questions = $this->aliasedQuestions('$'.$expr->name, $questionAliases);
        }

        if ($this->isCallExpressionType($exprType)) {
            /** @var MethodCall|NullsafeMethodCall|StaticCall $expr */
            $questions = $this->questionFromCall($expr);
        }

        if ($exprType === 'Expr_BooleanNot') {
            /** @var BooleanNot $expr */
            $questions = $this->questions($expr->expr, $questionAliases);
        }

        if ($this->isLogicalBinaryOpType($exprType)) {
            /** @var BinaryOp $expr */
            $questions = [...$this->questions($expr->left, $questionAliases), ...$this->questions($expr->right, $questionAliases)];
        }

        return $questions;
    }

    /**
     * Determine whether logical binary op type applies to the supplied input.
     */
    private function isLogicalBinaryOpType(string $exprType): bool
    {
        return in_array($exprType, ['Expr_BinaryOp_BooleanAnd', 'Expr_BinaryOp_BooleanOr', 'Expr_BinaryOp_LogicalAnd', 'Expr_BinaryOp_LogicalOr', 'Expr_BinaryOp_LogicalXor'], true);
    }
}
