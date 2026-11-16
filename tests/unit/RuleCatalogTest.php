<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\SodaInitFileEmitter;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\RuleMetadata;
use Cosmira\Soda\Rules\ExpressionCheck;
use Cosmira\Soda\Rules\Naming\MaxVariableNameWords;
use Cosmira\Soda\Rules\Structure\MaxPropertiesPerClass;
use Cosmira\Soda\Rules\Usage\NoNestedArrayAccess;
use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class RuleCatalogTest extends TestCase
{
    public function testDiscoveryIncludesPreviouslyUnregisteredChecksWithoutEnablingThem(): void
    {
        $definitions = RuleCatalog::definitions();
        foreach ([
            'max_variable_name_words' => MaxVariableNameWords::class,
            'no_nested_array_access'  => NoNestedArrayAccess::class,
        ] as $id => $class) {
            $this->assertSame($class, $definitions[$id]['class']);
            $this->assertFalse($definitions[$id]['standard']);
            $this->assertArrayNotHasKey($id, RuleCatalog::standardDefinitions());
        }
    }

    public function testDiscoveryReadsAttributesWithoutConstructingOptionalChecks(): void
    {
        $definitions = RuleCatalog::definitions();
        $class = MaxPropertiesPerClass::class;
        $reflection = new \ReflectionClass($class);
        $this->assertSame(1, $reflection->getConstructor()->getNumberOfRequiredParameters());
        $metadata = $reflection->getAttributes(Rule::class)[0]->newInstance();
        $definition = $definitions['max_properties_per_class'];
        $this->assertSame($metadata->label, $definition['label']);
        $this->assertSame($metadata->advice, $definition['advice']);
        $this->assertSame($metadata->severity, $definition['severity']);
        $this->assertSame($metadata->comparison, $definition['comparison']);
        $this->assertArrayNotHasKey('arguments', $definition);
        $this->assertNotContains(ExpressionCheck::class, array_column($definitions, 'class'));
    }

    public function testArchitecturalQuotasRequireExplicitSelection(): void
    {
        foreach (['max_properties_per_class', 'max_arguments', 'no_boolean_parameters', 'no_service_locator_calls', 'no_runtime_contract_probes', 'no_static_mutable_properties', 'boolean_methods_without_prefix'] as $id) {
            $definition = CheckFixture::definitions()[$id];
            self::assertArrayNotHasKey($id, CheckFixture::standardDefinitions());
            $class = $definition['class'];
            self::assertSame($id, (new $class(...$definition['arguments']))->id());
        }
    }

    public function testPromotedRulesAreEnabledInStandardAndGeneratedConfiguration(): void
    {
        $ids = ['no_empty_finally_blocks', 'max_cognitive_complexity', 'fragmented_owner_behavior', 'repeated_owner_concern', 'scattered_owner_concern', 'repeated_configuration_sequence', 'repeated_foreign_state_decision', 'repeated_foreign_state_update', 'max_efferent_coupling', 'no_dependency_cycles'];
        $init = SodaInitFileEmitter::emit();
        foreach ($ids as $id) {
            $entry = CheckFixture::standardDefinitions()[$id];
            $rule = new $entry['class'](...$entry['arguments']);
            self::assertSame($id, $rule->id());
            self::assertStringContainsString('new '.(new \ReflectionClass($rule))->getShortName().'(', $init);
        }
        self::assertStringContainsString('new MaxCognitiveComplexity(15)', $init);
        self::assertStringContainsString('new MaxEfferentCoupling(15)', $init);
    }

    public function testMetadataMatchesRuleMetadataDefault(): void
    {
        $fromCatalog = CheckFixture::definitions();
        $fromFacade = new RuleMetadata($fromCatalog);

        foreach ($fromCatalog as $id => $row) {
            $this->assertSame($row['severity'], $fromFacade->severity($id), $id);
            $this->assertSame($row['label'], $fromFacade->label($id), $id);
            $this->assertSame($row['advice'], $fromFacade->advice($id), $id);

            if (isset($row['comparison'])) {
                $this->assertSame($row['comparison'], $fromFacade->comparison($id), $id);
            }
        }
    }

    public function testRuleMetadataFallsBackForUnknownRules(): void
    {
        $metadata = new RuleMetadata([]);

        $this->assertSame(RuleMetadata::SEVERITY_WARNING, $metadata->severity('custom_rule'));
        $this->assertSame('Unknown:', $metadata->label('custom_rule'));
        $this->assertSame(RuleMetadata::COMPARISON_MAX, $metadata->comparison('custom_rule'));
        $this->assertNull($metadata->advice('custom_rule'));
        $this->assertSame([], $metadata->ruleKeys());
    }

    public function testDefinitionsExposeStablePublicMetadata(): void
    {
        $knownSections = ['structural', 'complexity', 'naming'];

        foreach (CheckFixture::definitions() as $id => $definition) {
            $identity = $definition;
            $presentation = $definition;

            $this->assertTrue(is_subclass_of($definition['class'], Check::class));
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', $id, $id);
            $this->assertContains($identity['section'], $knownSections, $id);
            $this->assertNotSame('', trim($presentation['label']), $id);
            $this->assertNotSame('', trim(CheckFixture::definitions()[$id]['advice']), $id);
            $this->assertContains($presentation['severity'], ['error', 'warning'], $id);

            if ($presentation['comparison'] !== null) {
                $this->assertContains($presentation['comparison'], ['min', 'max'], $id);
            }
        }
    }

    public function testDefinitionsExposeListRulesRows(): void
    {
        foreach (CheckFixture::definitions() as $id => $definition) {
            $row = ['id' => $id, ...$definition];

            $this->assertSame($id, $row['id']);
            $this->assertSame($definition['section'], $row['section'], $id);
            $this->assertSame($definition['severity'], $row['severity'], $id);
            $this->assertSame($definition['default'], $row['default'], $id);
            $this->assertSame($definition['label'], $row['label'], $id);
            $this->assertSame(CheckFixture::definitions()[$id]['advice'], $row['advice'], $id);

            $this->assertIsString($row['section'], $id);
            $this->assertContains($row['severity'], ['error', 'warning'], $id);
            $this->assertTrue(is_int($row['default']) || is_float($row['default']), $id);
            $this->assertNotSame('', trim($row['label']), $id);
            $this->assertNotSame('', trim($row['advice']), $id);
        }
    }

    public function testRuleLabelsAreUnique(): void
    {
        $idsByLabel = [];

        foreach (CheckFixture::definitions() as $id => $definition) {
            $label = $definition['label'];
            $idsByLabel[$label] ??= [];
            $idsByLabel[$label][] = $id;
        }

        foreach ($idsByLabel as $label => $ids) {
            $this->assertCount(
                1,
                $ids,
                sprintf('Rule label "%s" is shared by: %s', $label, implode(', ', $ids)),
            );
        }
    }

    public function testInitDefinitionsPointToExistingRuleCheckers(): void
    {
        foreach (CheckFixture::definitions() as $id => $entry) {
            $class = $entry['class'];
            $rule = new $class(...$entry['arguments']);
            $this->assertInstanceOf(Check::class, $rule);
            $this->assertSame($id, $rule->id());
        }
    }

    public function testCatalogAndBundleContainTheSameRuleClasses(): void
    {
        $expected = array_column(CheckFixture::standardDefinitions(), 'class');
        $actual = array_map(fn ($check) => $check::class, RuleCatalog::standard());
        $this->assertSame($expected, $actual);
        $this->assertCount(111, $actual);
        $ids = array_map(fn ($check) => $check->id(), RuleCatalog::standard());
        $this->assertContains('max_cognitive_complexity', $ids);
        $this->assertNotContains('no_redundant_rethrow', $ids);
        $this->assertContains('no_empty_finally_blocks', $ids);
        $this->assertContains('no_useless_return', $ids);
        $this->assertContains('no_useless_concat', $ids);
        $this->assertContains('no_redundant_boolean_returns', $ids);
        $this->assertContains('prefer_combined_conditions', $ids);
        $this->assertContains('no_redundant_boolean_comparisons', $ids);
        $this->assertArrayNotHasKey('max_disconnected_method_groups', CheckFixture::definitions());
    }
}
