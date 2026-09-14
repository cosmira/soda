<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

/**
 * @internal
 */
final class MethodOrderClassAnalysis
{
    /**
     * @var array<string, true>|null
     */
    private ?array $cycles = null;

    /**
     * @param list<MethodOrderMethod> $methods
     */
    public function __construct(
        public readonly string $name,
        public readonly array $methods,
    ) {}

    /**
     * Check a call-order constraint, ignoring contradictory edges within cycles.
     */
    public function isOutOfOrder(?string $first, string $second): bool
    {
        if ($first === null) {
            return false;
        }

        $this->cycles ??= $this->cyclicEdges();
        $isCyclic = isset($this->cycles[$first.'>'.$second]);
        if ($isCyclic) {
            return false;
        }

        $positions = array_flip(array_keys($this->methodsByName()));

        return $positions[$first] > $positions[$second];
    }

    /**
     * @return array<string, true>
     */
    private function cyclicEdges(): array
    {
        $graph = [];
        foreach ($this->methods as $caller) {
            $previous = null;
            foreach ($caller->calls as $call) {
                if ($previous !== null) {
                    $targets = $graph[$previous] ?? [];
                    $targets[] = $call;
                    $graph[$previous] = array_values(array_unique($targets));
                }

                $previous = $call;
            }
        }

        $cyclic = [];
        foreach ($graph as $from => $targets) {
            foreach ($targets as $to) {
                if ($this->hasPath($to, $from, $graph)) {
                    $cyclic[$from.'>'.$to] = true;
                }
            }
        }

        return $cyclic;
    }

    /**
     * @param array<string, list<string>> $graph
     * @param array<string, true>         $visited
     */
    private function hasPath(string $from, string $target, array $graph, array $visited = []): bool
    {
        if ($from === $target) {
            return true;
        }

        if (isset($visited[$from])) {
            return false;
        }

        $visited[$from] = true;

        foreach ($graph[$from] ?? [] as $next) {
            if ($this->hasPath($next, $target, $graph, $visited)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, MethodOrderMethod>
     */
    public function methodsByName(): array
    {
        $out = [];

        foreach ($this->methods as $method) {
            $out[$method->normalizedName] = $method;
        }

        return $out;
    }
}
