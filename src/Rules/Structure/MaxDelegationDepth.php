<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\Composition\BehaviorComposition;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;

/**
 * Limits consecutive transparent calls, rather than the size of each individual method.
 */
final class MaxDelegationDepth extends Check
{
    /**
     * Set the maximum number of consecutive forwarding methods.
     */
    public function __construct(private readonly int $limit = 3)
    {
        throw_if($limit < 1, \InvalidArgumentException::class, 'Delegation limit must be positive.');
    }

    /**
     * Report chains with source evidence; a forwarding cycle is always an error.
     */
    public function checkProject(ProjectFacts $project): iterable
    {
        $types = (new BehaviorComposition)->project($project);
        $methods = $this->methods($types);
        $reportedCycles = [];
        foreach ($methods as $start => $method) {
            [$path, $cursor] = $this->path($start, $methods);
            $cycle = isset($path[$cursor]);
            if (! $cycle && count($path) <= $this->limit) {
                continue;
            }

            $names = array_keys($path);
            if ($cycle) {
                $signature = $names;
                sort($signature);
                $key = implode('|', $signature);
                if (isset($reportedCycles[$key])) {
                    continue;
                }

                $reportedCycles[$key] = true;
            }

            yield new Violation(
                rule: $this->id(), file: $method['file'],
                value: count($path), threshold: $cycle ? 0 : $this->limit, method: $method['method'], class: $method['class'], line: $method['line'],
                message: ($cycle ? 'Transparent delegation cycle: ' : 'Transparent delegation chain: ').implode(' -> ', [...$names, $cursor]).'. Remove forwarding steps that add no operation.',
            );
        }
    }

    /**
     * Build one outgoing forwarding edge per composed method, retaining source locations.
     */
    private function methods(array $types): array
    {
        $methods = [];
        foreach ($types as $class => $type) {
            if ($type['kind'] !== 'class') {
                continue;
            }

            foreach ($type['methods'] as $name => $method) {
                $methods[$class.'::'.$name] = [
                    'target' => $this->target($method, $class, $types),
                    'line'   => $method['line'], 'file' => $method['file'],
                    'class'  => $type['name'], 'method' => $name,
                ];
            }
        }

        return $methods;
    }

    /**
     * Follow the transparent prefix until a real operation, unknown target or repeated method.
     */
    private function path(string $start, array $methods): array
    {
        $path = [];
        $cursor = $start;
        $method = $methods[$cursor] ?? [];
        while (($method['target'] ?? null) !== null && ! isset($path[$cursor])) {
            $path[$cursor] = true;
            $cursor = $method['target'];
            $method = $methods[$cursor] ?? [];
        }

        return [$path, $cursor];
    }

    /**
     * Resolve virtual receivers in the host class and private accesses in their lexical scope.
     */
    private function target(array $method, string $class, array $types): ?string
    {
        if ($method['target'] === null) {
            return null;
        }

        [$receiver, $name] = explode('::', $method['target'], 2);
        $scope = $method['scope'] ?? $class;
        $lexical = $types[$scope] ?? $types[$class];
        if ($receiver === '@self') {
            $methods = $lexical['methods'];
            $target = $methods[$name] ?? [];
            $receiver = ($target['visibility'] ?? null) === 'private' ? $scope : $class;
        }

        if ($receiver === '@scope') {
            $receiver = $scope;
        }

        if ($receiver === '@parent') {
            $receiver = $lexical['parent'] ?? null;
        }

        if (is_string($receiver) && str_starts_with($receiver, '@property:')) {
            $property = substr($receiver, strlen('@property:'));
            $receiver = $this->propertyReceiver($property, $scope, $lexical, $types[$class]);
        }

        return $receiver === null ? null : strtolower($receiver).'::'.$name;
    }

    /**
     * Resolve a dependency field against its lexical private slot or effective public receiver.
     */
    private function propertyReceiver(string $property, string $scope, array $lexical, array $host): ?string
    {
        $properties = $lexical['properties'];
        $origins = $lexical['propertyOrigins'];
        $isPrivate = ($properties[$property] ?? null) === 'private' && ($origins[$property] ?? null) === $scope;
        $owner = $isPrivate ? $lexical : $host;
        $types = $owner['propertyTypes'];
        $origins = $owner['propertyOrigins'];
        $receiver = $types[$property] ?? null;

        return $receiver === 'self' ? $origins[$property] : $receiver;
    }

    /**
     * Return the stable rule identifier.
     */
    public function id(): string
    {
        return 'max_delegation_depth';
    }

    /**
     * Request compact direct-call facts for the project graph.
     */
    public function requiredAnalyses(): array
    {
        return ['classBehavior'];
    }
}
