<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\Rule;

use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\Report\OccurrenceViolationFactory;
use Bunnivo\Soda\Quality\Report\Violation;
use Illuminate\Support\Collection;

use function sprintf;

final class MethodOrderChecker implements RuleChecker
{
    public const string RULE = 'methods_follow_call_order';

    #[\Override]
    public function check(EvaluationContext $context): Collection
    {
        if (! $context->config->isRuleEnabled(self::RULE)) {
            return collect();
        }

        $violations = [];
        $scanner = new MethodOrderNodeScanner();

        foreach (array_keys($context->fileMetrics->qualityMetrics()) as $file) {
            foreach ($scanner->scan($file) as $class) {
                array_push($violations, ...$this->violationsForClass($file, $class));
            }
        }

        $maxViolations = (int) $context->config->getRule(self::RULE);

        return collect(array_slice($violations, $maxViolations));
    }

    /**
     * @return list<Violation>
     */
    private function violationsForClass(string $file, MethodOrderClassAnalysis $class): array
    {
        $byName = $this->methodsByName($class);
        $actualPosition = array_flip(array_keys($byName));
        $violations = [];

        foreach ($class->methods as $caller) {
            array_push($violations, ...$this->violationsForCaller($file, $class, $caller, $byName, $actualPosition));
        }

        return $violations;
    }

    /**
     * @param array<string, MethodOrderMethod> $byName
     * @param array<string, int>               $actualPosition
     *
     * @return list<Violation>
     */
    private function violationsForCaller(
        string $file,
        MethodOrderClassAnalysis $class,
        MethodOrderMethod $caller,
        array $byName,
        array $actualPosition,
    ): array {
        if (count($caller->calls) < 2) {
            return [];
        }

        $violations = [];
        $previousCall = null;

        foreach ($caller->calls as $call) {
            if ($previousCall !== null && $actualPosition[$previousCall] > $actualPosition[$call]) {
                $violations[] = $this->violation($file, $class, $caller, $byName[$previousCall], $byName[$call]);
            }

            $previousCall = $call;
        }

        return $violations;
    }

    private function violation(
        string $file,
        MethodOrderClassAnalysis $class,
        MethodOrderMethod $caller,
        MethodOrderMethod $current,
        MethodOrderMethod $previous,
    ): Violation {
        return OccurrenceViolationFactory::build([
            'rule'      => self::RULE,
            'file'      => $file,
            'value'     => $current->line,
            'threshold' => $previous->line,
            'line'      => $current->line,
            'class'     => $class->name,
            'method'    => $current->name,
            'message'   => sprintf(
                '%s() should be declared before %s() because %s() calls it first',
                $current->name,
                $previous->name,
                $caller->name,
            ),
        ]);
    }

    /**
     * @return array<string, MethodOrderMethod>
     */
    private function methodsByName(MethodOrderClassAnalysis $class): array
    {
        $out = [];

        foreach ($class->methods as $method) {
            $out[$method->normalizedName] = $method;
        }

        return $out;
    }
}
