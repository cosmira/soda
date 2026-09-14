<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Scalar\Int_;
use PhpParser\NodeFinder;

/**
 * Forbids numeric literal array indices (`$a[0]`, `$a[-1]`, etc.).
 *
 * @example soda.php: `new NoNumericArrayIndex()`
 */
final class NoNumericArrayIndex extends Check
{
    /**
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Defines message used by this policy.
     */
    private const string MESSAGE = 'Numeric array index access is forbidden. Use named keys instead.';

    /**
     * Return the stable rule identifier used in configuration and reports.
     */
    #[\Override]
    public function id(): string
    {
        return 'no_numeric_array_index';
    }

    /**
     * Evaluate this rule using the already collected file facts.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $facts): iterable
    {
        foreach ((new NodeFinder)->findInstanceOf($facts->nodes, ArrayDimFetch::class) as $fetch) {
            $dimension = $fetch->dim;
            $isNumeric = $dimension instanceof Int_ || ($dimension instanceof UnaryMinus && $dimension->expr instanceof Int_);
            $shouldReport = $isNumeric && $fetch->getStartLine() > 0;
            if ($shouldReport) {
                yield new Violation(rule: $this->id(), file: $facts->path, value: 1, threshold: 0, line: $fetch->getStartLine(), message: self::MESSAGE);
            }
        }
    }
}
