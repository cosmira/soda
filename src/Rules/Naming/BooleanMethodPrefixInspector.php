<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Naming;

use function array_fill_keys;

use Cosmira\Soda\Analysis\ProjectFacts;

use function str_starts_with;

/**
 * @internal
 */
final readonly class BooleanMethodPrefixInspector
{
    /**
     * Method names and qualified method names explicitly ignored by the rule.
     *
     * @var array<string, true>
     */
    private array $ignored;

    /**
     * @var array<string, array{inherits: list<string>, methods: array<string, true>}>
     */
    private array $typeIndex;

    /**
     * @param list<string> $ignore
     * @param list<string> $allowedPrefixes
     */
    public function __construct(ProjectFacts $project, array $ignore, private array $allowedPrefixes)
    {
        $this->ignored = array_fill_keys($ignore, true);
        $this->typeIndex = BooleanMethodTypeIndexBuilder::build($project);
    }

    /**
     * @param array<string, mixed> $metrics
     *
     * @return list<array<string, mixed>>
     */
    public function violations(array $metrics): array
    {
        $badMethods = [];

        foreach ($this->methodsFromMetrics($metrics) as $methodData) {
            if ($this->shouldReportMethod($methodData)) {
                $badMethods[] = $methodData;
            }
        }

        return $badMethods;
    }

    /**
     * @param array<string, mixed> $metrics
     *
     * @return list<array<string, mixed>>
     */
    private function methodsFromMetrics(array $metrics): array
    {
        $naming = $metrics['naming'] ?? null;
        $methods = is_array($naming) ? ($naming['methods'] ?? null) : null;

        return is_array($methods) ? $methods : [];
    }

    /**
     * @param array<string, mixed> $methodData
     */
    private function shouldReportMethod(array $methodData): bool
    {
        $isBooleanMethod = $this->isBooleanClassMethod($methodData);
        if (! $isBooleanMethod) {
            return false;
        }

        $methodName = $methodData['methodName'];

        return ! $this->hasAllowedPrefix($methodName)
            && ! $this->isExcepted($methodData)
            && ! $this->isInheritedContract($methodData);
    }

    /**
     * @param array<string, mixed> $methodData
     */
    private function isBooleanClassMethod(array $methodData): bool
    {
        return ($methodData['class'] ?? null) !== null
            && $this->isBooleanReturnType($methodData['returnType'] ?? null)
            && is_string($methodData['methodName'] ?? null);
    }

    /**
     * Determine whether boolean return type applies to the supplied input.
     */
    private function isBooleanReturnType(mixed $returnType): bool
    {
        return is_string($returnType)
            && in_array('bool', explode('|', $returnType), true);
    }

    /**
     * Check the supplied input for allowed prefix.
     */
    private function hasAllowedPrefix(string $methodName): bool
    {
        foreach ($this->allowedPrefixes as $prefix) {
            if (str_starts_with($methodName, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $methodData
     */
    private function isExcepted(array $methodData): bool
    {
        $methodName = $methodData['methodName'] ?? null;
        $fullName = $methodData['name'] ?? null;
        $exceptMethods = $this->ignored;

        return (is_string($methodName) && isset($exceptMethods[$methodName]))
            || (is_string($fullName) && isset($exceptMethods[$fullName]));
    }

    /**
     * @param array<string, mixed> $methodData
     */
    private function isInheritedContract(array $methodData): bool
    {
        $isOverride = ($methodData['hasOverrideAttribute'] ?? false) === true;
        if ($isOverride) {
            return true;
        }

        $className = $methodData['class'] ?? null;
        $methodName = $methodData['methodName'] ?? null;

        return is_string($className)
            && is_string($methodName)
            && BooleanMethodInheritanceProbe::hasDeclaredMethod($this->typeIndex, $className, $methodName);
    }
}
