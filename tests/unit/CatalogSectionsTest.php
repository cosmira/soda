<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Config\RuleCatalog;
use PHPUnit\Framework\TestCase;

final class CatalogSectionsTest extends TestCase
{
    public function testSectionNames(): void
    {
        $names = array_values(array_unique(array_column(RuleCatalog::standardDefinitions(), 'section')));

        $this->assertSame(['structural', 'complexity', 'naming'], $names);
    }

    public function testSectionsContainExpectedRules(): void
    {
        $sections = [];
        foreach (RuleCatalog::standardDefinitions() as $id => $entry) {
            $sections[$entry['section']][] = $id;
        }

        $this->assertArrayHasKey('structural', $sections);
        $this->assertContains('max_method_length', $sections['structural']);
        $this->assertContains('max_class_length', $sections['structural']);
        $this->assertContains('max_classes_per_project', $sections['structural']);
        $this->assertContains('max_efferent_coupling', $sections['structural']);

        $this->assertArrayHasKey('complexity', $sections);
        $this->assertContains('max_cyclomatic_complexity', $sections['complexity']);
        $this->assertContains('max_control_nesting', $sections['complexity']);
        $this->assertContains('max_try_catch_blocks', $sections['complexity']);

        $this->assertArrayHasKey('naming', $sections);
        $this->assertContains('avoid_redundant_naming', $sections['naming']);
        $this->assertContains('namespace_name_length', $sections['naming']);
    }

    public function testRuleToSectionMapsAllRules(): void
    {
        $map = array_map(static fn (array $entry): string => $entry['section'], RuleCatalog::standardDefinitions());

        $this->assertSame('structural', $map['max_method_length']);
        $this->assertSame('structural', $map['max_efferent_coupling']);
        $this->assertSame('complexity', $map['max_cyclomatic_complexity']);
        $this->assertSame('complexity', $map['max_try_catch_blocks']);
        $this->assertSame('naming', $map['avoid_redundant_naming']);
        $this->assertSame('naming', $map['namespace_name_length']);
    }
}
