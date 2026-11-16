<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;

/**
 * Reports substr comparisons that ask the prefix or suffix question.
 */
#[Rule(
    label: 'Substring checks for prefixes and suffixes:',
    advice: 'Ask str_starts_with() and str_ends_with() instead of comparing substr() with a literal of the same length.',
)]
final class PreferStringStartsEndsWith extends Check
{
    /**
     * Map each forbidden substr call to its prefix and suffix question replacements.
     */
    private const REPLACEMENTS = [
        'substr'    => ['starts' => 'str_starts_with', 'ends' => 'str_ends_with'],
        'mb_substr' => ['starts' => 'mb_str_starts_with', 'ends' => 'mb_str_ends_with'],
    ];

    /**
     * Declare an AST-only check.
     *
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Return the stable rule identifier.
     */
    #[\Override]
    public function id(): string
    {
        return 'prefer_string_starts_ends_with';
    }

    /**
     * Report comparisons of substr results with string literals.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $facts): iterable
    {
        $comparisons = (new NodeFinder)->find($facts->nodes, static fn ($node): bool => $node instanceof BinaryOp && in_array($node->getOperatorSigil(), ['===', '!==', '==', '!='], true));
        foreach ($comparisons as $comparison) {
            assert($comparison instanceof BinaryOp);
            [$call, $literal] = $this->substrSide($comparison);
            if (! $call instanceof FuncCall || ! $literal instanceof String_) {
                continue;
            }

            $question = $this->question($call, $literal);
            if ($question === null) {
                continue;
            }

            $name = $this->substrName($call);
            $replacements = self::REPLACEMENTS[$name];
            $replacement = $replacements[$question];
            yield new Violation(
                rule: $this->id(),
                file: $facts->path,
                value: 1,
                threshold: 0,
                line: $comparison->getStartLine(),
                message: sprintf(
                    'Use %s() instead of comparing %s() with a literal; the answer reads as a question about the %s of the string.',
                    $replacement,
                    $name,
                    $question === 'starts' ? 'prefix' : 'suffix',
                ),
            );
        }
    }

    /**
     * Accept one side holding a substr call and the other a string literal.
     *
     * @return array{FuncCall|null, String_|null}
     */
    private function substrSide(BinaryOp $comparison): array
    {
        foreach ([$comparison->left, $comparison->right] as $side) {
            $other = $comparison->left === $side ? $comparison->right : $comparison->left;
            if (! $side instanceof FuncCall || ! $other instanceof String_ || $side->isFirstClassCallable()) {
                continue;
            }

            $name = $this->substrName($side);
            if (! isset(self::REPLACEMENTS[$name])) {
                continue;
            }

            return [$side, $other];
        }

        return [null, null];
    }

    /**
     * Return the lowercase function name when the call targets a plain name.
     */
    private function substrName(FuncCall $call): string
    {
        return $call->name instanceof Name ? $call->name->toLowerString() : '';
    }

    /**
     * Classify the call as a prefix or suffix question when the offsets match the literal.
     */
    private function question(FuncCall $call, String_ $literal): ?string
    {
        $offset = $this->offset($call);
        if ($offset === null) {
            return null;
        }

        $length = $this->declaredLength($call);
        $counted = $this->literalLength($call, $literal);

        if ($offset === 0) {
            return $length === $counted ? 'starts' : null;
        }

        return $this->suffixQuestion($offset, $length, $counted);
    }

    /**
     * Resolve the numeric offset, negating a unary minus literal.
     */
    private function offset(FuncCall $call): ?int
    {
        $offset = $call->getArgs()[1]->value;
        if ($offset instanceof LNumber) {
            return $offset->value;
        }

        if ($offset instanceof Expr\UnaryMinus && $offset->expr instanceof LNumber) {
            return -$offset->expr->value;
        }

        return null;
    }

    /**
     * Return the declared length when given as a literal integer.
     */
    private function declaredLength(FuncCall $call): ?int
    {
        $declared = $call->getArgs()[2]->value ?? null;
        if (! $declared instanceof LNumber) {
            return null;
        }

        return $declared->value;
    }

    /**
     * Measure the literal with the encoding of the substring function itself.
     */
    private function literalLength(FuncCall $call, String_ $literal): int
    {
        return $this->substrName($call) === 'mb_substr' ? mb_strlen($literal->value) : strlen($literal->value);
    }

    /**
     * Answer the suffix question when the offset reaches back over the literal.
     */
    private function suffixQuestion(int $offset, ?int $length, int $counted): ?string
    {
        if ($offset >= 0) {
            return null;
        }

        if ($length === null) {
            return 'ends';
        }

        $measuresLiteral = $length === $counted;
        $fitsBeforeStart = $length <= -$offset;

        return $measuresLiteral && $fitsBeforeStart ? 'ends' : null;
    }
}
