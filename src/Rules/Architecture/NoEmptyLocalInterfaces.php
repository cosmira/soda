<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Architecture;

use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;

/**
 * Rejects local marker interfaces unless an explicit external protocol requires them.
 */
final class NoEmptyLocalInterfaces extends Check
{
    /**
     * @param list<string> $protocols Qualified marker interfaces required by an external protocol.
     */
    public function __construct(private readonly array $protocols = []) {}

    /**
     * Report interfaces without a declared or inherited method contract.
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

        $protocols = array_fill_keys(array_map(strtolower(...), $this->protocols), true);
        foreach ($interfaces as $key => $declaration) {
            if (isset($protocols[$key]) || $this->hasContract($key, $interfaces)) {
                continue;
            }

            yield new Violation(
                rule: $this->id(), file: $declaration['file'], value: 1, threshold: 0,
                class: $declaration['name'], line: $declaration['line'],
                message: $declaration['name'].' declares no methods and inherits no known method contract. Remove the marker interface or declare its required protocol explicitly.',
            );
        }
    }

    /**
     * Follow parent interfaces across files, terminating on cycles.
     */
    private function hasContract(string $key, array $interfaces, array $seen = []): bool
    {
        if (isset($seen[$key])) {
            return false;
        }

        $seen[$key] = true;
        $declaration = $interfaces[$key] ?? null;
        $isExternalContract = $declaration === null && in_array($key, array_map(strtolower(...), $this->protocols), true);
        if ($isExternalContract || ($declaration['methods'] ?? []) !== []) {
            return true;
        }

        foreach ($declaration['parents'] ?? [] as $parent) {
            if ($this->hasContract($parent, $interfaces, $seen)) {
                return true;
            }
        }

        return false;
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
