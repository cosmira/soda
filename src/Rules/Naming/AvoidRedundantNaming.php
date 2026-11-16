<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Naming;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;

#[Rule(
    label: 'Redundant naming:',
    advice: 'Remove words already supplied by the class or namespace; let the local context carry that meaning.',
    default: 80,
)]
final class AvoidRedundantNaming extends Check
{
    /**
     * Set the similarity percentage needed for a finding.
     *
     * @param int $threshold Threshold used to decide whether to report the measured value.
     */
    public function __construct(private readonly int $threshold = 80) {}

    /**
     * Identify this rule in reports.
     */
    public function id(): string
    {
        return 'avoid_redundant_naming';
    }

    /**
     * Use the already parsed syntax tree.
     *
     * @return list<string>
     */
    public function requiredAnalyses(): array
    {
        return ['naming'];
    }

    /**
     * Compare names against the context already expressed by their owners.
     *
     * @return iterable<Violation>
     */
    public function checkFile(FileFacts $file): iterable
    {
        $naming = $file->metrics['naming'] ?? [];
        if (! array_key_exists('types', $naming)) {
            yield from $this->violations($file->path, $naming);
        }
    }

    /**
     * Source-defined names and inheritance contracts are complete only after project collection.
     */
    public function checkProject(ProjectFacts $project): iterable
    {
        if ($this->threshold <= 0) {
            return;
        }

        $types = $this->typeIndex($project);
        $contexts = [];
        foreach ($types as $name => $type) {
            $inherited = [];
            foreach ($type['inherits'] ?? [] as $parent) {
                $inherited += $this->inheritedMethods(strtolower($parent), $types);
            }

            $concrete = array_filter($inherited, static fn (array $method): bool => ! $method['abstract']);
            $contexts[$name] = ['contracts' => $inherited + $this->traitRequirements($name, $types),
                'occupied'                  => array_keys(($type['methodDeclarations'] ?? []) + $concrete),
                'resolved'                  => $this->hasCompleteAncestry($name, $types)];
        }

        foreach ($project->files as $file => $metrics) {
            $naming = $metrics['naming'] ?? [];
            if (array_key_exists('types', $naming)) {
                yield from $this->violations($file, $this->namingContext($naming, $types, $contexts));
            }
        }
    }

    /**
     * Legacy direct facts retain their local behavior without pretending that project contracts exist.
     */
    private function violations(string $file, array $naming): iterable
    {
        if ($this->threshold <= 0 || ! isset($naming['classes'], $naming['methods'])) {
            return;
        }

        $threshold = $this->normalisedSimilarityThreshold($this->threshold);
        $analyzer = new RedundantNamingAnalyzer($threshold);
        foreach ($analyzer->analyze($naming) as $finding) {
            yield $this->toViolation($file, $finding, (int) $threshold);
        }
    }

    /**
     * Conditional or duplicated declarations cannot donate native naming contracts to another type.
     */
    private function typeIndex(ProjectFacts $project): array
    {
        $index = [];
        foreach ($project->files as $metrics) {
            $naming = $metrics['naming'] ?? [];
            foreach ($naming['types'] ?? [] as $type) {
                $name = strtolower($type['name']);
                $type['eligible'] = ($type['unconditional'] ?? false) && ! ($type['ambiguous'] ?? false) && ! isset($index[$name]);
                $index[$name] = $type;
            }
        }

        return $index;
    }

    /**
     * A missing or ambiguous ancestor can still require the current public method name.
     */
    private function hasCompleteAncestry(string $name, array $types, array $seen = []): bool
    {
        $type = $types[$name] ?? [];
        if (isset($seen[$name]) || ! ($type['eligible'] ?? false) || ! ($type['traitsResolved'] ?? false)) {
            return false;
        }

        $seen[$name] = true;
        foreach ([...$type['inherits'], ...$type['traits']] as $ancestor) {
            if (! $this->hasCompleteAncestry(strtolower($ancestor), $types, $seen)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Visible source ancestor declarations constrain names; private parent methods remain separate bindings.
     */
    private function inheritedMethods(string $name, array $types, array $seen = []): array
    {
        $type = $types[$name] ?? [];
        if (isset($seen[$name]) || ! ($type['eligible'] ?? false)) {
            return [];
        }

        $seen[$name] = true;
        $methods = [];
        foreach ($type['inherits'] ?? [] as $parent) {
            $methods += $this->inheritedMethods(strtolower($parent), $types, $seen);
        }

        foreach ($type['methodDeclarations'] ?? [] as $method => $declaration) {
            if (! $declaration['private']) {
                $methods[$method] = $declaration;
            }
        }

        return $methods;
    }

    /**
     * A concrete intermediate trait discharges its exported abstract name requirement.
     */
    private function traitRequirements(string $name, array $types, array $seen = []): array
    {
        $type = $types[$name] ?? [];
        if (isset($seen[$name]) || ! ($type['eligible'] ?? false)) {
            return [];
        }

        $seen[$name] = true;
        $required = [];
        foreach ($type['traits'] ?? [] as $trait) {
            $trait = strtolower($trait);
            $source = $types[$trait] ?? [];
            if (($source['kind'] ?? null) === 'trait') {
                $required += $this->traitRequirements($trait, $types, $seen);
            }
        }

        if ($type['kind'] === 'trait') {
            foreach ($type['methodDeclarations'] ?? [] as $method => $declaration) {
                if ($declaration['abstract']) {
                    $required[$method] = $declaration;
                } else {
                    unset($required[$method]);
                }
            }
        }

        return $required;
    }

    /**
     * New project proofs apply only to the exact direct declaration at the method's source byte position.
     */
    private function namingContext(array $naming, array $types, array $contexts): array
    {
        $naming['existingTypes'] = array_keys(array_filter($types, static fn (array $type): bool => $type['eligible']));
        $methods = [];
        foreach ($naming['methods'] as $method) {
            $name = strtolower($method['class'] ?? '');
            $owner = $types[$name] ?? [];
            $declarations = $owner['methodDeclarations'] ?? [];
            $key = strtolower($method['methodName']);
            $declaration = $declarations[$key] ?? [];
            $start = $method['declarationStart'] ?? -1;
            if (($owner['eligible'] ?? false) && $start >= 0 && ($declaration['start'] ?? null) === $start) {
                $context = $contexts[$name];
                $method['hasUnresolvedNameContract'] = ! $context['resolved'];
                $method['hasNativeNameContract'] = array_key_exists($key, $context['contracts']);
                $method['occupiedMethods'] = $context['occupied'];
            }

            $methods[] = $method;
        }

        $naming['methods'] = $methods;

        return $naming;
    }

    /**
     * Convert the configured similarity percentage into a fractional cutoff.
     */
    private function normalisedSimilarityThreshold(int $threshold): float
    {
        $isPercentage = $threshold >= 1 && $threshold <= 100;

        return $isPercentage ? (float) $threshold : 80.0;
    }

    /**
     * Convert a redundant-name suggestion into a diagnostic with its original location.
     *
     * @param array{type: string, current: string, suggested: string, reason: string, similarity: float, line: int, className?: string} $finding
     */
    private function toViolation(string $file, array $finding, int $threshold): Violation
    {
        $similarity = (int) round($finding['similarity']);
        $isMethod = $finding['type'] === 'method' && isset($finding['className']);
        $class = $finding['type'] === 'class' ? $finding['current'] : null;
        if ($isMethod) {
            $class = $finding['className'];
        }

        $method = $isMethod ? strstr($finding['current'].'(', '(', true) : null;

        return new Violation(
            rule: $this->id(), file: $file, value: $similarity, threshold: $threshold, method: $method, class: $class, line: $finding['line'],
            message: sprintf('Redundant naming: %s (%d%%)', $this->compactMessage($finding['current'], $finding['suggested']), $similarity),
        );
    }

    /**
     * Remove repeated rule text from a diagnostic displayed beside its heading.
     */
    private function compactMessage(string $current, string $suggested): string
    {
        $cur = str_contains($current, '(') ? substr($current, 0, (int) strpos($current, '(')) : $current;
        $sug = str_contains($suggested, '(') ? substr($suggested, 0, (int) strpos($suggested, '(')) : $suggested;

        return $cur.' → '.$sug;
    }
}
