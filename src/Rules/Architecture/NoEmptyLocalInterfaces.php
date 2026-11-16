<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Architecture;

use Cosmira\Soda\Analysis\Dependencies\ExternalParameterContracts;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;

/**
 * Rejects local marker interfaces unless an explicit external protocol requires them.
 */
#[Rule(
    label: 'Empty local interfaces:',
    advice: 'Remove decorative marker interfaces; list required external protocols explicitly.',
    severity: 'error',
)]
final class NoEmptyLocalInterfaces extends Check
{
    /**
     * Allow marker interfaces required by explicitly named external protocols.
     *
     * @param list<string> $protocols Qualified marker interfaces required by an external protocol.
     */
    public function __construct(
        /**
         * External marker interfaces exempted from the empty-contract restriction.
         */
        private readonly array $protocols = [],
    ) {}

    /**
     * Report interfaces whose complete declared and inherited contract is empty.
     */
    public function checkProject(ProjectFacts $project): iterable
    {
        $interfaces = [];
        foreach ($project->files as $file => $facts) {
            $usage = $facts['interfaceUsage'] ?? [];
            foreach ($usage['declarations'] ?? [] as $key => $declaration) {
                $interfaces[$key] = $declaration + ['file' => $file];
            }
        }

        $contracts = ExternalParameterContracts::resolve($interfaces, array_keys($project->files));
        $protocols = array_fill_keys(array_map(strtolower(...), $this->protocols), true);
        foreach ($interfaces as $key => $declaration) {
            if (isset($protocols[$key]) || ! $this->isEmpty($key, $contracts)) {
                continue;
            }

            yield new Violation(
                rule: $this->id(), file: $declaration['file'], value: 1, threshold: 0,
                class: $declaration['name'], line: $declaration['line'],
                message: $declaration['name'].' declares no methods or properties and its complete inherited contract is empty. Remove the marker interface or declare its required protocol explicitly.',
            );
        }
    }

    /**
     * Every reachable parent must be known and empty before a marker is proven.
     */
    private function isEmpty(string $key, array $interfaces, array $seen = []): bool
    {
        if (isset($seen[$key])) {
            return true;
        }

        $seen[$key] = true;
        if (in_array($key, array_map(strtolower(...), $this->protocols), true)) {
            return false;
        }

        $declaration = $interfaces[$key] ?? null;
        if ($declaration === null) {
            return $this->isEmptyNativeInterface($key);
        }

        if (! $declaration['isInterface'] || $declaration['hasProperties'] || $declaration['methods'] !== []) {
            return false;
        }

        foreach ($declaration['parents'] as $parent) {
            if (! $this->isEmpty($parent, $interfaces, $seen)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Only internal interfaces expose trusted runtime contracts without autoloading.
     */
    private function isEmptyNativeInterface(string $name): bool
    {
        if (! interface_exists($name, false)) {
            return false;
        }

        $interface = new \ReflectionClass($name);

        return $interface->isInternal() && $interface->getMethods() === [] && $interface->getProperties() === [];
    }

    /**
     * Identify this check in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'no_empty_local_interfaces';
    }

    /**
     * Declare the fact collectors required by this check.
     */
    public function requiredAnalyses(): array
    {
        return ['interfaceUsage'];
    }
}
