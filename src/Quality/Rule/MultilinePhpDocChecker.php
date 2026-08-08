<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\Rule;

use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\Report\OccurrenceViolationFactory;
use Bunnivo\Soda\Quality\Report\Violation;
use Illuminate\Support\Collection;

use function sprintf;

final class MultilinePhpDocChecker implements RuleChecker
{
    public const string METHOD_RULE = 'multiline_method_phpdoc';

    public const string PROPERTY_RULE = 'multiline_property_phpdoc';

    private const array DEFAULT_VISIBILITIES = [
        self::METHOD_RULE   => ['public'],
        self::PROPERTY_RULE => ['public'],
    ];

    #[\Override]
    public function check(EvaluationContext $context): Collection
    {
        $rules = array_filter(
            [self::METHOD_RULE, self::PROPERTY_RULE],
            fn (string $rule): bool => $context->config->isRuleEnabled($rule),
        );

        if ($rules === []) {
            return collect();
        }

        $violations = [];
        $scanner = new MultilinePhpDocNodeScanner();

        foreach (array_keys($context->fileMetrics->qualityMetrics()) as $file) {
            foreach ($scanner->scan($file, $rules) as $occurrence) {
                if ($this->shouldReport($context, $occurrence)) {
                    $ruleViolations = $violations[$occurrence->rule] ?? [];
                    $ruleViolations[] = $this->violation($file, $occurrence);
                    $violations[$occurrence->rule] = $ruleViolations;
                }
            }
        }

        return collect([
            ...$this->afterBudget($context, self::METHOD_RULE, $violations[self::METHOD_RULE] ?? []),
            ...$this->afterBudget($context, self::PROPERTY_RULE, $violations[self::PROPERTY_RULE] ?? []),
        ]);
    }

    private function shouldReport(EvaluationContext $context, MultilinePhpDocOccurrence $occurrence): bool
    {
        if ($occurrence->hasMultilinePhpDoc) {
            return false;
        }

        return in_array($occurrence->visibility, $this->visibilities($context, $occurrence->rule), true);
    }

    /**
     * @return list<'public'|'protected'|'private'>
     */
    private function visibilities(EvaluationContext $context, string $rule): array
    {
        $options = $context->config->ruleOptions($rule);
        $visibilities = $options['visibilities'] ?? self::DEFAULT_VISIBILITIES[$rule];

        if (! is_array($visibilities)) {
            return self::DEFAULT_VISIBILITIES[$rule];
        }

        return array_values(array_filter(
            $visibilities,
            static fn (mixed $visibility): bool => in_array($visibility, ['public', 'protected', 'private'], true),
        ));
    }

    /**
     * @param list<Violation> $violations
     *
     * @return list<Violation>
     */
    private function afterBudget(EvaluationContext $context, string $rule, array $violations): array
    {
        return array_slice($violations, (int) $context->config->getRule($rule));
    }

    private function violation(string $file, MultilinePhpDocOccurrence $occurrence): Violation
    {
        $target = $occurrence->rule === self::METHOD_RULE ? 'method' : 'property';

        return OccurrenceViolationFactory::build([
            'rule'      => $occurrence->rule,
            'file'      => $file,
            'value'     => 0,
            'threshold' => 1,
            'line'      => $occurrence->location->line,
            'class'     => $occurrence->location->class,
            'method'    => $occurrence->rule === self::METHOD_RULE ? $occurrence->name : null,
            'message'   => sprintf(
                '%s %s::%s must have a multiline PHPDoc comment',
                ucfirst($occurrence->visibility),
                $target,
                $occurrence->name,
            ),
        ]);
    }
}
