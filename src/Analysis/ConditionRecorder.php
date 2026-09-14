<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * @internal
 */
final class ConditionRecorder
{
    /**
     * @var array<string, list<array{line: int, count: int}>>
     */
    private array $conditionsByMethod = [];

    /**
     * Start a separate condition list for this callable.
     */
    public function openMethod(string $method): void
    {
        $this->conditionsByMethod[$method] = [];
    }

    /**
     * Record the operand count and source line of a condition in its callable.
     */
    public function record(Node $node, ?string $method): void
    {
        if ($method === null) {
            return;
        }

        $cond = ConditionExtractor::extract($node);
        if ($cond instanceof Expr) {
            $count = BooleanOperandCounter::count($cond);
            if ($count > 0) {
                $key = $method;
                $list = $this->conditionsByMethod[$key] ?? [];
                $list[] = [
                    'line'  => $cond->getStartLine(),
                    'count' => $count,
                ];
                $this->conditionsByMethod[$key] = $list;
            }
        }
    }

    /**
     * @psalm-return array<string, list<array{line: int, count: int}>>
     */
    public function conditionsByMethod(): array
    {
        return $this->conditionsByMethod;
    }
}
