<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\SodaConfig;
use Cosmira\Soda\Rules\Check as RuleChecker;
use Cosmira\Soda\Rules\Complexity\MaxCyclomaticComplexity as MethodRules;
use Cosmira\Soda\Rules\Complexity\NoAssignmentInCondition;
use Cosmira\Soda\Rules\Complexity\NoComplexControlConditions;
use Cosmira\Soda\Rules\Complexity\NoElseBranches;
use Cosmira\Soda\Rules\Naming\AvoidRedundantNaming as RedundantNamingChecker;
use Cosmira\Soda\Rules\Naming\BooleanMethodPrefix as BooleanMethodPrefixChecker;
use Cosmira\Soda\Rules\Naming\ClassNameLength as NameLengthChecker;
use Cosmira\Soda\Rules\Structure\MaxClassLength as ClassRules;
use Cosmira\Soda\Rules\Structure\MaxLineLength as LineLengthChecker;
use Cosmira\Soda\Rules\Structure\NoTrivialDelegatingClasses;
use Cosmira\Soda\Rules\Usage\NoNumericArrayIndex;
use Cosmira\Soda\Rules\Usage\NoUnusedMethods;
use Cosmira\Soda\Rules\Usage\OnlyListArraysAllowed;
use Cosmira\Soda\Rules\Usage\UselessVariableRule;
use PHPUnit\Framework\TestCase;

final class CatalogPresetsTest extends TestCase
{
    public function testStandardRulesContainsAllBuiltinRules(): void
    {
        $checkers = RuleCatalog::standard();

        $this->assertNotEmpty($checkers);

        $classNames = array_map(fn (RuleChecker $c) => $c::class, $checkers);
        $this->assertContains(ClassRules::class, $classNames);
        $this->assertContains(MethodRules::class, $classNames);
        $this->assertContains(RedundantNamingChecker::class, $classNames);
        $this->assertContains(BooleanMethodPrefixChecker::class, $classNames);
        $this->assertContains(NameLengthChecker::class, $classNames);
        $this->assertContains(OnlyListArraysAllowed::class, $classNames);
        $this->assertContains(NoNumericArrayIndex::class, $classNames);
        $this->assertContains(NoAssignmentInCondition::class, $classNames);
        $this->assertContains(NoComplexControlConditions::class, $classNames);
        $this->assertContains(NoElseBranches::class, $classNames);
        $this->assertContains(NoUnusedMethods::class, $classNames);
        $this->assertContains(NoTrivialDelegatingClasses::class, $classNames);
        $this->assertContains(UselessVariableRule::class, $classNames);
    }

    public function testStructuralRulesContainsClassRules(): void
    {
        $classNames = array_map(fn (RuleChecker $c) => $c::class, RuleCatalog::checks('structural'));
        $this->assertContains(ClassRules::class, $classNames);
        $this->assertContains(LineLengthChecker::class, $classNames);
        $this->assertContains(NoTrivialDelegatingClasses::class, $classNames);
    }

    public function testStandardRulesDoesNotRegisterDuplicateCheckerClasses(): void
    {
        $classNames = array_map(fn (RuleChecker $c) => $c::class, RuleCatalog::standard());

        $this->assertSame(array_values(array_unique($classNames)), $classNames);
    }

    public function testComplexityRulesContainsMethodRules(): void
    {
        $classNames = array_map(fn (RuleChecker $c) => $c::class, RuleCatalog::checks('complexity'));
        $this->assertContains(MethodRules::class, $classNames);
    }

    public function testNamingRulesContainsNamingCheckers(): void
    {
        $classNames = array_map(fn (RuleChecker $c) => $c::class, RuleCatalog::checks('naming'));
        $this->assertContains(RedundantNamingChecker::class, $classNames);
        $this->assertContains(BooleanMethodPrefixChecker::class, $classNames);
        $this->assertContains(NameLengthChecker::class, $classNames);
    }

    // --- section selection via with() ---

    public function testSodaConfigSelectedCatalogSections(): void
    {
        $config = (new SodaConfig)
            ->with(RuleCatalog::checks('structural'))
            ->with(RuleCatalog::checks('complexity'));

        $checkers = $config->checks();

        $classNames = array_map(fn (RuleChecker $c) => $c::class, $checkers);
        $this->assertContains(ClassRules::class, $classNames);
        $this->assertContains(MethodRules::class, $classNames);
        $this->assertNotContains(RedundantNamingChecker::class, $classNames);
    }

    public function testQualityEngineAcceptsExtraCheckers(): void
    {
        $engine = CheckFixture::selected(CheckFixture::checks(), [null]);

        $this->assertNotEmpty($engine);
    }

    public function testQualityEngineWithAllBuiltins(): void
    {
        $builtins = RuleCatalog::standard();
        $engine = CheckFixture::selected(CheckFixture::checks(), [...$builtins, null]);

        $this->assertNotEmpty($engine);
    }
}
