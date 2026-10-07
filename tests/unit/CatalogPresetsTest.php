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
        $this->assertNotContains(BooleanMethodPrefixChecker::class, $classNames);
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

    public function testStandardPhpDocRulesCoverAllVisibilities(): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'soda-standard-phpdoc-');
        self::assertIsString($temporary);
        $file = $temporary.'.php';
        rename($temporary, $file);
        file_put_contents($file, <<<'PHP'
<?php
final class DocumentedMembers
{
    public const PUBLIC_VALUE = 1;
    protected const PROTECTED_VALUE = 2;
    private const PRIVATE_VALUE = 3;

    public string $publicValue;
    protected string $protectedValue;
    private string $privateValue;

    public function publicOperation(): void {}
    protected function protectedOperation(): void {}
    private function privateOperation(): void {}
}
PHP);

        $rules = array_filter(
            RuleCatalog::standard(),
            static fn (RuleChecker $rule): bool => str_starts_with($rule->id(), 'multiline_'),
        );

        try {
            $violations = CheckFixture::collect($file, $rules)['violations'];
        } finally {
            unlink($file);
        }

        self::assertCount(3, $rules);
        self::assertCount(9, $violations);
        foreach (['method', 'property', 'constant'] as $member) {
            $messages = array_map(
                static fn ($violation): string => $violation->message,
                array_filter($violations, static fn ($violation): bool => $violation->rule === 'multiline_'.$member.'_phpdoc'),
            );
            foreach (['Public', 'Protected', 'Private'] as $visibility) {
                self::assertCount(1, array_filter($messages, static fn (string $message): bool => str_starts_with($message, $visibility)));
            }
        }
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
        $this->assertNotContains(BooleanMethodPrefixChecker::class, $classNames);
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
