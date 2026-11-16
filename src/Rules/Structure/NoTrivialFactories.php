<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\Dependencies\ExternalParameterContracts;
use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\InheritedParameterContracts;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;

/**
 * Reports methods that only forward construction arguments, regardless of decorative metadata.
 */
#[Rule(
    label: 'Trivial factories:',
    advice: 'Consider direct construction. Preserve parameter and return type contracts, named arguments and useful creation boundaries.',
)]
final class NoTrivialFactories extends Check
{
    /**
     * Preserve explicitly identified published entry points, never decorative source markers.
     *
     * @param list<string> $contracts Qualified Class::method names whose API must remain available.
     */
    public function __construct(private readonly array $contracts = []) {}

    use ForwardedConstruction;

    /**
     * Direct file consumers retain local behavior; the Runner defers to project facts.
     */
    public function checkFile(FileFacts $file): iterable
    {
        if (array_key_exists('factoryConstruction', $file->metrics)) {
            return;
        }

        yield from $this->violations($file->path, self::factoryConstruction($file->nodes), $this->hookTypes($file), []);
    }

    /**
     * Match observed native inputs against the selected open declaration in this project.
     */
    public function checkProject(ProjectFacts $project): iterable
    {
        $types = [];
        $calls = [];
        foreach ($project->files as $facts) {
            $construction = $facts['factoryConstruction'] ?? [];
            foreach ($construction['types'] ?? [] as $name => $type) {
                $types[$name] = isset($types[$name]) ? ['open' => false, 'methods' => [], 'parent' => null, 'traits' => true] : $type;
            }

            array_push($calls, ...($construction['calls'] ?? []));
        }

        $observed = [];
        foreach ($calls as $call) {
            $origin = $this->incomingFactoryOrigin($types, $call['receiver'], $call['method'], $call['arity']);
            if ($origin !== null) {
                $observed[$origin] = true;
            }
        }

        foreach ($project->files as $file => $facts) {
            $construction = $facts['factoryConstruction'] ?? [];
            if (($construction['candidates'] ?? []) === []) {
                continue;
            }

            $hooks = ExternalParameterContracts::resolve($facts['parameterContracts'] ?? [], [$file]);
            yield from $this->violations($file, $construction, $hooks, $observed);
        }
    }

    /**
     * Trait adaptations and unresolved ancestry do not establish an incoming dispatch target.
     */
    private function incomingFactoryOrigin(array $types, string $receiver, string $method, int $arity): ?string
    {
        $bound = $types[$receiver] ?? [];
        if (! ($bound['open'] ?? false)) {
            return null;
        }

        $seen = [];
        $current = $receiver;
        while (isset($types[$current]) && ! isset($seen[$current])) {
            $seen[$current] = true;
            $type = $types[$current];
            if ($type['traits']) {
                return null;
            }

            $methods = $type['methods'];
            if (isset($methods[$method])) {
                $selected = $methods[$method];

                return $selected['open'] && $selected['arity'] === $arity ? $selected['origin'] : null;
            }

            $current = strtolower($type['parent'] ?? '');
        }

        return null;
    }

    /**
     * Preserve diagnostic provenance and existing declared hook contracts for every candidate.
     */
    private function violations(string $file, array $construction, array $hooks, array $observed): iterable
    {
        $contracts = array_fill_keys(array_map(strtolower(...), $this->contracts), true);
        foreach ($construction['candidates'] ?? [] as $candidate) {
            if (isset($contracts[$candidate['identity']]) || isset($observed[$candidate['identity']])
                || $this->isRequired($hooks, $candidate['class'], $candidate['method'])) {
                continue;
            }

            yield new Violation(
                rule: $this->id(), file: $file, value: 1, threshold: 0,
                method: $candidate['method'], class: $candidate['class'], line: $candidate['line'],
                message: sprintf('%s::%s() only constructs %s and forwards all parameters unchanged. Consider constructing %s at the call site.',
                    $candidate['class'], $candidate['method'], $candidate['creation'], $candidate['creation']),
            );
        }
    }

    /**
     * Identify this standard check in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'no_trivial_factories';
    }

    /**
     * Retain scalar creation candidates and source contract declarations until project resolution.
     *
     * @return list<string>
     */
    public function requiredAnalyses(): array
    {
        return ['factoryConstruction'];
    }

    /**
     * Use source declarations only; no application class is instantiated or autoloaded.
     */
    private function hookTypes(FileFacts $file): array
    {
        $types = InheritedParameterContracts::collect($file->nodes);

        return ExternalParameterContracts::resolve($types, [$file->path]);
    }

    /**
     * A hook requires positive contract evidence, not a conventional method name alone.
     */
    private function isRequired(array $types, string $class, string $method): bool
    {
        $class = strtolower($class);
        $method = strtolower($method);
        $pending = [$class];
        $visited = [];
        while ($pending !== []) {
            $name = array_pop($pending);
            if (isset($visited[$name])) {
                continue;
            }

            $visited[$name] = true;
            $type = $types[$name] ?? [];
            $methods = $type['methods'] ?? [];
            $signature = $methods[$method] ?? [];
            $isInherited = $name !== $class && ! ($signature['private'] ?? true);
            if ($isInherited || $this->isDispatchMatch($type['factoryDispatch'] ?? [], $method)) {
                return true;
            }

            array_push($pending, ...($type['parents'] ?? []), ...array_map(strtolower(...), $type['traitNames'] ?? []));
        }

        return false;
    }

    /**
     * Match method names proved by calls on the declaring receiver.
     */
    private function isDispatchMatch(array $patterns, string $method): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $method) === 1) {
                return true;
            }
        }

        return false;
    }
}
