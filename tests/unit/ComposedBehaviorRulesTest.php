<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\FactCollector;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Rules\Architecture\NoRepeatedTypeDispatch;
use Cosmira\Soda\Rules\Check;
use Cosmira\Soda\Rules\Complexity\NoRepeatedCompoundConditions;
use Cosmira\Soda\Rules\Structure\NoTrivialDelegatingClasses;
use PHPUnit\Framework\TestCase;

final class ComposedBehaviorRulesTest extends TestCase
{
    public function testForwardingTraitCannotHideAWrapper(): void
    {
        $findings = $this->findings(new NoTrivialDelegatingClasses, [
            '<?php namespace App; trait Forwarding { function run($item) { return $this->next->save($item); } }',
            '<?php namespace App; class Wrapper { use Forwarding; function __construct(private Service $next) {} }',
        ]);
        self::assertCount(1, $findings);
        self::assertSame('App\\Wrapper', $findings[0]->class);
        self::assertSame('run', $findings[0]->method);
    }

    public function testKnownParentPrivateDependencyDoesNotExemptSubclass(): void
    {
        $findings = $this->findings(new NoTrivialDelegatingClasses, [
            '<?php abstract class BaseWrapper { function __construct(private Service $next) {} function run($item) { return $this->next->save($item); } }',
            '<?php final class Wrapper extends BaseWrapper {}',
        ]);
        self::assertCount(1, $findings);
        self::assertSame('Wrapper', $findings[0]->class);
    }

    public function testPolicyInTraitPreservesRealBehavior(): void
    {
        self::assertSame([], $this->findings(new NoTrivialDelegatingClasses, [
            '<?php trait Policy { function run($item) { authorize($item); return $this->next->save($item); } }',
            '<?php class Wrapper { use Policy; function __construct(private Service $next) {} }',
        ]));
    }

    public function testParentTraitAndOwnConditionsAreCountedTogether(): void
    {
        $findings = $this->findings(new NoRepeatedCompoundConditions, [
            '<?php abstract class BaseState { protected bool $active; protected bool $paused; function first() { if ($this->active && !$this->paused) {} } }',
            '<?php trait Reminder { function second() { if ($this->active && !$this->paused) {} } }',
            '<?php class Subscription extends BaseState { use Reminder; function third() { if ($this->active && !$this->paused) {} } }',
        ]);
        self::assertCount(1, $findings);
        self::assertSame('Subscription', $findings[0]->class);
        self::assertSame(3, $findings[0]->value);
        self::assertSame(2, $findings[0]->threshold);
        self::assertStringContainsString('first()', $findings[0]->message);
        self::assertStringContainsString('second()', $findings[0]->message);
        self::assertStringContainsString('third()', $findings[0]->message);
    }

    public function testAliasesDoNotMultiplyTheSameConditionBody(): void
    {
        self::assertSame([], $this->findings(new NoRepeatedCompoundConditions, [
            '<?php trait Conditions { function first() { if ($this->active && !$this->paused) {} } }',
            '<?php class Subscription { private bool $active; private bool $paused; use Conditions { first as second; first as third; } }',
        ]));
    }

    public function testPrecedenceAndOverridesSelectActualBodies(): void
    {
        self::assertSame([], $this->findings(new NoRepeatedCompoundConditions, [
            '<?php trait First { function run() { if ($this->active && !$this->paused) {} } } trait Second { function run() {} }',
            '<?php class Subscription { private bool $active; private bool $paused; use First, Second { Second::run insteadof First; } function next() { if ($this->active && !$this->paused) {} } function last() { if ($this->active && !$this->paused) {} } }',
        ]));
    }

    public function testPrivateRedeclarationsDoNotMergeDifferentStateSlots(): void
    {
        self::assertSame([], $this->findings(new NoRepeatedCompoundConditions, [
            '<?php class BaseState { private bool $active; private bool $paused; function first() { if ($this->active && !$this->paused) {} } }',
            '<?php class ChildState extends BaseState { private bool $active; private bool $paused; function second() { if ($this->active && !$this->paused) {} } function third() { if ($this->active && !$this->paused) {} } }',
        ]));
    }

    public function testPrivateShadowDoesNotDisableOwnRepeatedDecision(): void
    {
        $findings = $this->findings(new NoRepeatedCompoundConditions, [
            '<?php class BaseState { private bool $active; private bool $paused; function first() { if ($this->active && !$this->paused) {} } }',
            '<?php class ChildState extends BaseState { private bool $active; private bool $paused; function second() { if ($this->active && !$this->paused) {} } function third() { if ($this->active && !$this->paused) {} } function fourth() { if ($this->active && !$this->paused) {} } }',
        ]);
        self::assertCount(1, $findings);
        self::assertSame(3, $findings[0]->value);
        self::assertSame('ChildState', $findings[0]->class);
    }

    public function testHookedAndStaticStateAreNotOrdinarySnapshotReads(): void
    {
        foreach (['public bool $active { get => true; }', 'private static bool $active;'] as $property) {
            self::assertSame([], $this->findings(new NoRepeatedCompoundConditions, [
                '<?php trait Conditions { function first() { if ($this->active && !$this->paused) {} } function second() { if ($this->active && !$this->paused) {} } function third() { if ($this->active && !$this->paused) {} } }',
                '<?php class State { use Conditions; '.$property.' private bool $paused; }',
            ]));
        }
    }

    public function testOnlyConfiguredBoundaryContractsExemptForwarding(): void
    {
        $sources = [
            '<?php interface Port { function run($item); } class ParentWrapper implements Port { function __construct(private Service $next) {} function run($item) { return $this->next->save($item); } }',
            '<?php class ChildWrapper extends ParentWrapper {}',
        ];
        self::assertCount(2, $this->findings(new NoTrivialDelegatingClasses, $sources));
        self::assertSame([], $this->findings(new NoTrivialDelegatingClasses(contracts: ['Port']), $sources));
        self::assertCount(2, $this->findings(new NoTrivialDelegatingClasses(contracts: ['UnrelatedPort']), $sources));
    }

    public function testTypeDispatchAcrossTraitsAndParentMethods(): void
    {
        $rule = new NoRepeatedTypeDispatch;
        $findings = $this->findings($rule, [
            '<?php abstract class BaseDispatch { function first($item) { return $item instanceof A || $item instanceof B; } }',
            '<?php trait Dispatch { function second($item) { return match($item::class) { A::class => 1, B::class => 2 }; } }',
            '<?php class Handler extends BaseDispatch { use Dispatch; }',
        ]);
        self::assertCount(1, $findings);
        self::assertSame('Handler', $findings[0]->class);
        self::assertSame(2, $findings[0]->value);
    }

    public function testRelativeConstantsRetainTheirLexicalOwner(): void
    {
        $parent = '<?php class BaseState { const LIMIT = 1; protected int $amount; protected bool $active; function first() { if ($this->amount === self::LIMIT && $this->active) {} } }';
        $child = '<?php class ChildState extends BaseState { const LIMIT = 2; function second() { if ($this->amount === self::LIMIT && $this->active) {} } function third() { if ($this->amount === self::LIMIT && $this->active) {} } }';
        self::assertSame([], $this->findings(new NoRepeatedCompoundConditions, [$parent, $child]));
        $lateParent = str_replace('self::LIMIT', 'static::LIMIT', $parent);
        $lateChild = str_replace('self::LIMIT', 'static::LIMIT', $child);
        self::assertCount(1, $this->findings(new NoRepeatedCompoundConditions, [$lateParent, $lateChild]));
    }

    private function findings(Check $rule, array $sources): array
    {
        $project = new ProjectFacts;
        $findings = [];
        foreach ($sources as $source) {
            $path = tempnam(sys_get_temp_dir(), 'soda-composed-');
            file_put_contents($path, $source);

            try {
                $file = (new FactCollector)->collect($path, $rule->requiredAnalyses());
                $project->add($file);
                array_push($findings, ...$rule->checkFile($file));
            } finally {
                unlink($path);
            }
        }

        return [...$findings, ...$rule->checkProject($project)];
    }
}
