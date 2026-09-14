<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

/** Owns nesting depth for each active callable; anonymous scopes are suspended. */
final class NestingTracker
{
    /**
     * @var array<string, array{depth: int, line: int}>
     */
    private array $nestingByMethod = [];

    /**
     * @var list<array{name: string|null, depth: int}>
     */
    private array $scopes = [];

    /**
     * Stores name for this analysis instance.
     */
    private ?string $name = null;

    /**
     * Stores depth for this analysis instance.
     */
    private int $depth = 0;

    /**
     * Start method using the current analysis state.
     */
    public function startMethod(string $name, int $line): void
    {
        $this->enterClosure();
        $this->name = $name;
        $this->nestingByMethod[$name] = ['depth' => 0, 'line' => $line];
    }

    /**
     * End method using the current analysis state.
     */
    public function endMethod(): void
    {
        $previous = array_pop($this->scopes);
        $this->name = $previous['name'];
        $this->depth = $previous['depth'];
    }

    /**
     * Suspend named-callable accounting while traversing an anonymous scope.
     */
    public function enterClosure(): void
    {
        $this->scopes[] = ['name' => $this->name, 'depth' => $this->depth];
        $this->name = null;
        $this->depth = 0;
    }

    /**
     * Resume the enclosing scope after an anonymous callable.
     */
    public function leaveClosure(): void
    {
        $this->endMethod();
    }

    /**
     * Push control using the current analysis state.
     */
    public function pushControl(int $line): void
    {
        $this->depth++;
        if ($this->name === null) {
            return;
        }

        $maximum = $this->nestingByMethod[$this->name];
        if ($this->depth > $maximum['depth']) {
            $this->nestingByMethod[$this->name] = ['depth' => $this->depth, 'line' => $line];
        }
    }

    /**
     * Pop control using the current analysis state.
     */
    public function popControl(): void
    {
        $this->depth--;
    }

    /**
     * @return array<string, array{depth: int, line: int}>
     */
    public function nestingByMethod(): array
    {
        return $this->nestingByMethod;
    }
}
