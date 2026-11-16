<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\SodaInitFileEmitter;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\RuleMetadata;
use PHPUnit\Framework\TestCase;

final class RuleCatalogTest extends TestCase
{
    public function testArchitecturalQuotasRequireExplicitSelection(): void
    {
        foreach (['max_properties_per_class', 'max_arguments', 'no_boolean_parameters', 'no_service_locator_calls', 'no_runtime_contract_probes', 'no_static_mutable_properties', 'boolean_methods_without_prefix'] as $id) {
            $definition = RuleCatalog::definitions()[$id];
            self::assertArrayNotHasKey($id, RuleCatalog::standardDefinitions());
            $class = $definition['class'];
            self::assertSame($id, (new $class(...$definition['arguments']))->id());
        }
    }

    public function testPromotedRulesAreEnabledInStandardAndGeneratedConfiguration(): void
    {
        $ids = ['no_empty_finally_blocks', 'max_cognitive_complexity', 'fragmented_owner_behavior', 'repeated_owner_concern', 'scattered_owner_concern', 'repeated_configuration_sequence', 'repeated_foreign_state_decision', 'repeated_foreign_state_update', 'max_efferent_coupling', 'no_dependency_cycles'];
        $init = SodaInitFileEmitter::emit();
        foreach ($ids as $id) {
            $entry = RuleCatalog::standardDefinitions()[$id];
            $rule = new $entry['class'](...$entry['arguments']);
            self::assertSame($id, $rule->id());
            self::assertStringContainsString('new '.(new \ReflectionClass($rule))->getShortName().'(', $init);
        }
        self::assertStringContainsString('new MaxCognitiveComplexity(15)', $init);
        self::assertStringContainsString('new MaxEfferentCoupling(15)', $init);
    }

    public function testMetadataMatchesRuleMetadataDefault(): void
    {
        $fromCatalog = RuleCatalog::definitions();
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

        foreach (RuleCatalog::definitions() as $id => $definition) {
            $identity = $definition;
            $presentation = $definition;

            $this->assertTrue(is_subclass_of($definition['class'], Check::class));
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', $id, $id);
            $this->assertContains($identity['section'], $knownSections, $id);
            $this->assertNotSame('', trim($presentation['label']), $id);
            $this->assertNotSame('', trim(RuleCatalog::definitions()[$id]['advice']), $id);
            $this->assertContains($presentation['severity'], ['error', 'warning'], $id);

            if ($presentation['comparison'] !== null) {
                $this->assertContains($presentation['comparison'], ['min', 'max'], $id);
            }
        }
    }

    public function testDefinitionsExposeListRulesRows(): void
    {
        foreach (RuleCatalog::definitions() as $id => $definition) {
            $row = ['id' => $id, ...$definition];

            $this->assertSame($id, $row['id']);
            $this->assertSame($definition['section'], $row['section'], $id);
            $this->assertSame($definition['severity'], $row['severity'], $id);
            $this->assertSame($definition['default'], $row['default'], $id);
            $this->assertSame($definition['label'], $row['label'], $id);
            $this->assertSame(RuleCatalog::definitions()[$id]['advice'], $row['advice'], $id);

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

        foreach (RuleCatalog::definitions() as $id => $definition) {
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
        foreach (RuleCatalog::definitions() as $id => $entry) {
            $class = $entry['class'];
            $rule = new $class(...$entry['arguments']);
            $this->assertInstanceOf(Check::class, $rule);
            $this->assertSame($id, $rule->id());
        }
    }

    public function testCatalogAndBundleContainTheSameRuleClasses(): void
    {
        $expected = array_column(RuleCatalog::standardDefinitions(), 'class');
        $actual = array_map(fn ($check) => $check::class, RuleCatalog::standard());
        $this->assertSame($expected, $actual);
        $this->assertCount(109, $actual);
        $ids = array_map(fn ($check) => $check->id(), RuleCatalog::standard());
        $this->assertContains('max_cognitive_complexity', $ids);
        $this->assertNotContains('no_redundant_rethrow', $ids);
        $this->assertContains('no_empty_finally_blocks', $ids);
        $this->assertContains('no_useless_return', $ids);
        $this->assertContains('no_useless_concat', $ids);
        $this->assertContains('no_redundant_boolean_returns', $ids);
        $this->assertContains('prefer_combined_conditions', $ids);
        $this->assertContains('no_redundant_boolean_comparisons', $ids);
        $this->assertArrayNotHasKey('max_disconnected_method_groups', RuleCatalog::definitions());
    }
}
