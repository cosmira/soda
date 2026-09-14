<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

/** Interpret receivers in the lexical scope of each possible consuming type. */
trait UnusedMethodResolution
{
    /**
     * Inspect all bodies without requiring a reachable entrypoint.
     */
    private function calls(string $consumer, array $surface): void
    {
        foreach ($surface['bodies'] as $body) {
            foreach ($body['calls'] as $call) {
                $this->markCall($consumer, $body['scope'], $call);
            }
        }
    }

    /**
     * Lexical self and parent differ from late-bound this/static receivers.
     */
    private function markCall(string $consumer, string $scope, array $call): void
    {
        $type = $this->types[$scope];
        $target = match ($call['receiver']) {
            'this', 'static' => $consumer,
            'self'           => $scope,
            'parent'         => $type['parent'],
            default          => $call['receiver'],
        };
        if ($target === null) {
            return;
        }

        $methods = $this->surface($target)['methods'];
        $lexical = $this->surface($scope)['methods'];
        $private = $this->privateMethods($lexical, $scope);
        if (in_array($call['receiver'], ['this', 'self'], true)) {
            $methods = array_replace($methods, $private);
        }

        $selected = $call['method'] === null ? $methods : array_intersect_key($methods, [$call['method'] => true]);
        foreach ($selected as $method) {
            if ($this->isVisible($method, $scope)) {
                $this->used[$method['origin']] = true;
            }
        }
    }

    /**
     * Private dispatch uses the declaring lexical scope even on a child instance.
     */
    private function privateMethods(array $methods, string $scope): array
    {
        return array_filter($methods, static fn (array $method): bool => $method['visibility'] === 'private' && $method['scope'] === $scope);
    }

    /**
     * Foreign calls cannot reach private methods; protected access requires related types.
     */
    private function isVisible(array $method, string $scope): bool
    {
        if ($method['visibility'] === 'public' || $method['scope'] === $scope) {
            return true;
        }

        if ($method['visibility'] === 'private') {
            return false;
        }

        return $this->isAncestor($scope, $method['scope']) || $this->isAncestor($method['scope'], $scope);
    }

    /**
     * Bound ancestry to configured declarations and terminate cycles.
     */
    private function isAncestor(string $child, string $ancestor): bool
    {
        $seen = [];
        while (isset($this->types[$child]) && ! isset($seen[$child])) {
            $seen[$child] = true;
            $type = $this->types[$child];
            $parent = $type['parent'];
            if ($parent === $ancestor) {
                return true;
            }

            if ($parent === null) {
                return false;
            }

            $child = $parent;
        }

        return false;
    }
}
