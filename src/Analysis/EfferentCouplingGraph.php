<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use function array_pop;
use function count;
use function strcasecmp;

/**
 * Mutable Ce state: dependency edges and class/trait stack.
 *
 * @internal
 */
final class EfferentCouplingGraph
{
    /**
     * @var array<string, array<string, true>>
     */
    private array $dependencies = [];

    /**
     * @var list<array{name: non-empty-string, parent: ?non-empty-string}>
     */
    private array $frames = [];

    /**
     * @psalm-param non-empty-string $name
     * @psalm-param non-empty-string|null $parent
     */
    public function pushFrame(string $name, ?string $parent): void
    {
        $this->frames[] = ['name' => $name, 'parent' => $parent];
        $this->dependencies[$name] ??= [];
    }

    /**
     * Pop frame using the current analysis state.
     */
    public function popFrame(): void
    {
        if ($this->frames !== []) {
            array_pop($this->frames);
        }
    }

    /**
     * @psalm-return non-empty-string|null
     */
    public function currentOwner(): ?string
    {
        $last = array_key_last($this->frames);
        $frame = $last === null ? null : $this->frames[$last];

        return $frame['name'] ?? null;
    }

    /**
     * @psalm-return non-empty-string|null
     */
    public function currentParent(): ?string
    {
        $last = array_key_last($this->frames);
        $frame = $last === null ? null : $this->frames[$last];

        return $frame['parent'] ?? null;
    }

    /**
     * @psalm-param non-empty-string $fqcn
     */
    public function addEdge(string $fqcn): void
    {
        $owner = $this->currentOwner();

        if ($owner === null) {
            return;
        }

        $isSelfReference = strcasecmp($fqcn, $owner) === 0;

        if ($isSelfReference) {
            return;
        }

        $edges = $this->dependencies[$owner] ?? [];
        $edges[$fqcn] = true;
        $this->dependencies[$owner] = $edges;
    }

    /**
     * @psalm-return array<string, int>
     */
    public function couplingCountsByClass(): array
    {
        $out = [];

        foreach ($this->dependencies as $class => $set) {
            $out[$class] = count($set);
        }

        return $out;
    }

    /**
     * @return array<string, list<string>>
     */
    public function dependenciesByClass(): array
    {
        return array_map(array_keys(...), $this->dependencies);
    }
}
