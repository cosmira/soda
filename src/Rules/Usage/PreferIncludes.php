<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;

/**
 * Reports strpos comparisons that ask the substring question.
 */
#[Rule(
    label: 'Position checks for substrings:',
    advice: 'Ask str_contains() or mb_str_contains() instead of comparing strpos() with false.',
)]
final class PreferIncludes extends Check
{
    /**
     * Map each forbidden position call to the substring-question replacement.
     */
    private const REPLACEMENTS = [
        'strpos'    => 'str_contains',
        'mb_strpos' => 'mb_str_contains',
    ];

    /**
     * Return the stable rule identifier.
     */
    #[\Override]
    public function id(): string
    {
        return 'prefer_includes';
    }

    /**
     * Report comparisons of strpos results with false.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $facts): iterable
    {
        $comparisons = (new NodeFinder)->find($facts->nodes, static fn ($node): bool => $node instanceof BinaryOp\Identical
            || $node instanceof BinaryOp\NotIdentical
            || $node instanceof BinaryOp\Equal
            || $node instanceof BinaryOp\NotEqual);
        foreach ($comparisons as $comparison) {
            assert($comparison instanceof BinaryOp);
            $call = $this->positionCall($comparison);
            if (! $call instanceof FuncCall) {
                continue;
            }

            $name = $call->name instanceof Name ? $call->name->toLowerString() : '';
            yield new Violation(
                rule: $this->id(),
                file: $facts->path,
                value: 1,
                threshold: 0,
                line: $comparison->getStartLine(),
                message: sprintf('Use %s() instead of comparing %s() with false; the answer reads as a question about the substring.', self::REPLACEMENTS[$name], $name),
            );
        }
    }

    /**
     * Accept one side holding a strpos call and the other holding the false constant.
     */
    private function positionCall(BinaryOp $comparison): ?FuncCall
    {
        foreach ([$comparison->left, $comparison->right] as $side) {
            $other = $comparison->left === $side ? $comparison->right : $comparison->left;
            if (! $side instanceof FuncCall || ! $this->isFalse($other)) {
                continue;
            }

            $name = $side->name instanceof Name ? $side->name->toLowerString() : '';
            if ($side->isFirstClassCallable() || ! isset(self::REPLACEMENTS[$name])) {
                continue;
            }

            return $side;
        }

        return null;
    }

    /**
     * Report whether the expression is the false constant.
     */
    private function isFalse(Expr $expression): bool
    {
        return $expression instanceof ConstFetch && $expression->name->toLowerString() === 'false';
    }
}
