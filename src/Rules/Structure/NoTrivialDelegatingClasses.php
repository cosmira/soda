<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\Composition\BehaviorComposition;
use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use Cosmira\Soda\Rules\Structure\Delegation\TrivialDelegatingClassAnalyser;

/**
 * Reports behavior-free classes that only pass their operations through to one dependency.
 *
 * @example soda.php: `new NoTrivialDelegatingClasses()`
 */
final class NoTrivialDelegatingClasses extends Check
{
    /**
     * Preserve exact established boundary classes or interfaces supplied by the owning project.
     *
     * @param list<string> $contracts Qualified boundary class or interface names.
     */
    public function __construct(private readonly array $contracts = []) {}

    /**
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return ['classBehavior'];
    }

    /**
     * Evaluate inherited and trait-composed wrappers after all declarations are available.
     */
    public function checkProject(ProjectFacts $project): iterable
    {
        foreach ((new BehaviorComposition)->project($project) as $type) {
            if (! $this->isTransparentComposition($type)) {
                continue;
            }

            $methods = $type['methods'];
            unset($methods['__construct']);
            $first = reset($methods);
            yield new Violation(
                rule: $this->id(), file: $type['file'], value: 1, threshold: 0, method: count($methods) === 1 ? $first['name'] : null,
                class: $type['name'], line: $type['line'],
                message: $type['name'].' only forwards unchanged arguments through its composed operations: '.implode(', ', array_keys($methods)).'. Remove the transparent wrapper; traits and inheritance do not add a responsibility.',
            );
        }
    }

    /**
     * Require a concrete composed class whose complete surface contributes only forwarding.
     */
    private function isTransparentComposition(array $type): bool
    {
        $isComposed = $type['parent'] !== null || $type['uses'] !== [];
        $isConcrete = $type['kind'] === 'class' && ! $type['abstract'];
        if (! $isComposed || ! $isConcrete || $type['unknown'] !== [] || $this->isContract($type)) {
            return false;
        }

        $methods = $type['methods'];
        $constructor = $methods['__construct'] ?? ['constructor' => '*'];
        unset($methods['__construct']);
        $delegates = array_unique(array_column($methods, 'delegate'));
        $delegate = reset($delegates);
        $isSingleDelegate = count($delegates) === 1 && is_string($delegate);

        return $isSingleDelegate && array_keys($type['properties']) === [$delegate]
            && in_array($constructor['constructor'], ['*', $delegate], true);
    }

    /**
     * Evaluate this rule using the already collected file facts.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $facts): iterable
    {
        $file = $facts->path;
        $findings = (new TrivialDelegatingClassAnalyser)->analyse($facts->nodes);
        $violations = [];

        foreach ($findings as $finding) {
            $declarations = $facts->metrics['classBehavior'] ?? [];
            $type = $declarations[strtolower($finding->class)] ?? ['name' => $finding->class];
            if ($this->isContract($type)) {
                continue;
            }

            $methods = implode(', ', $finding->methods);
            $isSingleMethod = count($finding->methods) === 1;
            $violations[] = new Violation(
                rule: $this->id(),
                file: $file,
                value: 1,
                threshold: 0,
                method: $isSingleMethod ? current($finding->methods) : null,
                class: $finding->class,
                line: $finding->line,
                message: sprintf(
                    '%s only forwards unchanged arguments to $this->%s in: %s. Call the collaborator directly unless this boundary has a concrete responsibility; adding an interface alone does not simplify it.',
                    $finding->class,
                    $finding->delegate,
                    $methods,
                ),
            );
        }

        return $violations;
    }

    /**
     * Source markers alone never exempt a class; a matching configured contract is required.
     */
    private function isContract(array $type): bool
    {
        $identities = [strtolower($type['name']), ...($type['contracts'] ?? [])];

        return array_intersect($identities, array_map(strtolower(...), $this->contracts)) !== [];
    }

    /**
     * Return the stable rule identifier used in configuration and reports.
     */
    #[\Override]
    public function id(): string
    {
        return 'no_trivial_delegating_classes';
    }
}
