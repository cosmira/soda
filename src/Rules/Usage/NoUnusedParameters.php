<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use Cosmira\Soda\Analysis\Dependencies\ExternalParameterContracts;
use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\Frameworks\LaravelMiddlewareContracts;
use Cosmira\Soda\Analysis\InheritedParameterContracts;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;

/**
 * Reports unused inputs while preserving positions required by known parent contracts.
 */
final class NoUnusedParameters extends Check
{
    /**
     * @param list<string> $contracts Exact qualified method/function names with externally fixed signatures.
     */
    public function __construct(private readonly array $contracts = []) {}

    /**
     * Direct file checks resolve local declarations; the runner defers to complete project facts.
     */
    public function checkFile(FileFacts $file): iterable
    {
        if (array_key_exists('parameterInputs', $file->metrics)) {
            return;
        }

        $types = InheritedParameterContracts::collect($file->nodes);
        yield from $this->findings($file->path, ParameterInputFacts::collect($file->nodes), new InheritedParameterContracts($types), LaravelMiddlewareContracts::collect($file->nodes));
    }

    /**
     * Resolve contracts after every file's declarations have been collected.
     */
    public function checkProject(ProjectFacts $project): iterable
    {
        $types = [];
        $middleware = [];
        foreach ($project->files as $facts) {
            $types += $facts['parameterContracts'] ?? [];
            $middleware += $facts['registeredMiddleware'] ?? [];
        }

        $types = ExternalParameterContracts::resolve($types, array_keys($project->files));
        $inherited = new InheritedParameterContracts($types);
        foreach ($project->files as $path => $facts) {
            yield from $this->findings($path, $facts['parameterInputs'] ?? [], $inherited, $middleware);
        }
    }

    /**
     * Preserve required positions without exempting extra inputs on the same method.
     */
    private function findings(string $path, array $candidates, InheritedParameterContracts $inherited, array $middleware): iterable
    {
        $contracts = array_fill_keys(array_map(strtolower(...), $this->contracts), true);
        foreach ($candidates as $candidate) {
            $identity = $candidate['identity'];
            $arity = $inherited->arity($candidate['class'], $candidate['method']);
            $registered = isset($middleware[strtolower($candidate['class'] ?? '')]);
            $terminate = $registered && strtolower($candidate['method'] ?? '') === 'terminate' && ($candidate['publicInstance'] ?? false);
            $arity = max($arity, $terminate ? 2 : 0);
            $isExternal = isset($contracts[strtolower($identity)]);
            $isPolymorphic = $inherited->isUsedByOverride($candidate['class'], $candidate['method'], $candidate['position']);
            if ($isExternal || $candidate['position'] < $arity || $isPolymorphic) {
                continue;
            }

            yield new Violation(
                rule: $this->id(), file: $path, value: 1, threshold: 0,
                method: $candidate['method'], class: $candidate['class'], line: $candidate['line'],
                message: sprintf(
                    'Parameter $%s is never used by %s. Remove it if the signature is owned here; preserve required contract or positional callback parameters.',
                    $candidate['name'], $identity !== '' ? $identity : 'this callable',
                ),
            );
        }
    }

    /**
     * Identify this check in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'no_unused_parameters';
    }

    /**
     * Retain scalar input candidates and inheritance signatures for the project pass.
     */
    public function requiredAnalyses(): array
    {
        return ['parameterInputs'];
    }
}
