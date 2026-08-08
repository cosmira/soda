<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Tests;

use Bunnivo\Soda\Config\SodaConfig;
use Bunnivo\Soda\Plugins\BreathingPlugin;
use Bunnivo\Soda\Plugins\ComplexityPlugin;
use Bunnivo\Soda\Plugins\NamingPlugin;
use Bunnivo\Soda\Plugins\StandardPlugin;
use Bunnivo\Soda\Plugins\StructuralPlugin;
use Bunnivo\Soda\Quality\QualityConfig;
use Bunnivo\Soda\Quality\QualityEngine;
use Bunnivo\Soda\Quality\Rule\BooleanMethodPrefixChecker;
use Bunnivo\Soda\Quality\Rule\BreathingChecker;
use Bunnivo\Soda\Quality\Rule\ClassRules;
use Bunnivo\Soda\Quality\Rule\LineLengthChecker;
use Bunnivo\Soda\Quality\Rule\MethodRules;
use Bunnivo\Soda\Quality\Rule\NameLengthChecker;
use Bunnivo\Soda\Quality\Rule\RedundantNamingChecker;
use Bunnivo\Soda\Quality\Rule\RuleChecker;
use PHPUnit\Framework\TestCase;

final class BuiltinPluginsTest extends TestCase
{
    public function testStandardPluginContainsAllBuiltinRules(): void
    {
        $checkers = (new StandardPlugin)->checkers();

        $this->assertNotEmpty($checkers);

        $classNames = array_map(fn (RuleChecker $c) => $c::class, $checkers);
        $this->assertContains(ClassRules::class, $classNames);
        $this->assertContains(MethodRules::class, $classNames);
        $this->assertContains(BreathingChecker::class, $classNames);
        $this->assertContains(RedundantNamingChecker::class, $classNames);
        $this->assertContains(BooleanMethodPrefixChecker::class, $classNames);
        $this->assertContains(NameLengthChecker::class, $classNames);
    }

    public function testStructuralPluginContainsClassRules(): void
    {
        $classNames = array_map(fn (RuleChecker $c) => $c::class, (new StructuralPlugin)->checkers());
        $this->assertContains(ClassRules::class, $classNames);
        $this->assertContains(LineLengthChecker::class, $classNames);
    }

    public function testComplexityPluginContainsMethodRules(): void
    {
        $classNames = array_map(fn (RuleChecker $c) => $c::class, (new ComplexityPlugin)->checkers());
        $this->assertContains(MethodRules::class, $classNames);
    }

    public function testBreathingPluginContainsBreathingChecker(): void
    {
        $classNames = array_map(fn (RuleChecker $c) => $c::class, (new BreathingPlugin)->checkers());
        $this->assertContains(BreathingChecker::class, $classNames);
    }

    public function testNamingPluginContainsNamingCheckers(): void
    {
        $classNames = array_map(fn (RuleChecker $c) => $c::class, (new NamingPlugin)->checkers());
        $this->assertContains(RedundantNamingChecker::class, $classNames);
        $this->assertContains(BooleanMethodPrefixChecker::class, $classNames);
    }

    // --- plugin cherry-picking via with() ---

    public function testSodaConfigCherryPickedPlugins(): void
    {
        $config = (new SodaConfig)
            ->plugin(StructuralPlugin::class)
            ->plugin(ComplexityPlugin::class);

        $checkers = $config->pluginCheckers();

        $classNames = array_map(fn (RuleChecker $c) => $c::class, $checkers);
        $this->assertContains(ClassRules::class, $classNames);
        $this->assertContains(MethodRules::class, $classNames);
        $this->assertNotContains(BreathingChecker::class, $classNames);
        $this->assertNotContains(RedundantNamingChecker::class, $classNames);
    }

    public function testQualityEngineAcceptsExtraCheckers(): void
    {
        $engine = QualityEngine::create(QualityConfig::default(), [new BreathingChecker]);

        $this->assertInstanceOf(QualityEngine::class, $engine);
    }

    public function testQualityEngineWithAllBuiltins(): void
    {
        $builtins = (new StandardPlugin)->checkers();
        $engine = QualityEngine::create(QualityConfig::default(), [...$builtins, new BreathingChecker]);

        $this->assertInstanceOf(QualityEngine::class, $engine);
    }
}
