<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Contracts\ResearchCheck;
use PHPUnit\Framework\TestCase;

final class DocumentationContractTest extends TestCase
{
    public function testRuleIndexCoversCatalogOnceWithAccurateSelectionAndResultStatuses(): void
    {
        $index = $this->readProjectFile('docs/rules/README.md');
        preg_match_all(
            '/^\| \[([a-z_]+)\]\(([^)]+)\) \| [^|\n]+ \| (yes|no) \| (yes|no) \|$/m',
            $index,
            $rows,
            PREG_SET_ORDER,
        );

        $definitions = RuleCatalog::definitions();
        $expectedIds = array_keys($definitions);
        $actualIds = array_column($rows, 1);
        sort($expectedIds);
        sort($actualIds);
        $this->assertSame($expectedIds, $actualIds);

        foreach ($rows as [, $id, $page, $enabled, $affectsResult]) {
            $definition = $definitions[$id];
            $this->assertSame($id.'.md', $page);
            $this->assertFileExists(dirname(__DIR__, 2).'/docs/rules/'.$page);
            $this->assertSame(($definition['standard'] ?? true) ? 'yes' : 'no', $enabled, $id);
            $this->assertSame(
                is_a($definition['class'], ResearchCheck::class, true) ? 'no' : 'yes',
                $affectsResult,
                $id,
            );
        }
    }

    public function testReportDocsDescribeViolationOnlyJsonOutput(): void
    {
        $reportDocs = $this->readProjectFile('docs/QUALITY_REPORT_JSON.md');

        $this->assertStringContainsString('`passed`', $reportDocs);
        $this->assertStringContainsString('Quality gate result', $reportDocs);
        $this->assertStringContainsString('command exit status', $reportDocs);
        $this->assertStringContainsString('current schema is **4**', strtolower($reportDocs));
        $this->assertStringNotContainsString('`metrics`', $reportDocs);
        $this->assertStringNotContainsString('soda analyze', $reportDocs);
    }

    public function testRuleAuthoringChecklistMentionsFullListRulesMetadata(): void
    {
        $guide = $this->readProjectFile('docs/ADDING_A_QUALITY_RULE.md');

        $this->assertStringContainsString(
            'Ensure `php soda list:rules` shows the rule id, section, severity, default, and label.',
            $guide,
        );
    }

    public function testStructuralRuleDocsExposeCatalogHeadingsAndStandaloneExamples(): void
    {
        $structuralDocs = $this->readProjectFile('docs/STRUCTURAL_METRICS.md');

        foreach ($this->ruleIdsForSection('structural') as $ruleId) {
            $this->assertStringContainsString('### '.$ruleId, $structuralDocs, $ruleId);
        }

        foreach ([
            'new NoAssignmentInCondition()',
            'new NoComplexControlConditions()',
            "new MultilineConstantPhpDoc(['public'])",
            'new NoElseBranches()',
            'new NoTrivialDelegatingClasses()',
            'new OnlyListArraysAllowed()',
            'new NoNumericArrayIndex()',
            "new NoUnusedMethods(ignore: ['seedDatabase'])",
            'new UselessVariableRule()',
        ] as $configExample) {
            $this->assertStringContainsString($configExample, $structuralDocs);
        }
    }

    public function testComplexityAndNamingDocsExposeCatalogHeadings(): void
    {
        $complexityDocs = $this->readProjectFile('docs/COMPLEXITY_READABILITY_METRICS.md');
        $namingDocs = $this->readProjectFile('docs/NAMING_RULES.md');

        foreach ($this->ruleIdsForSection('complexity') as $ruleId) {
            $this->assertStringContainsString('### '.$ruleId, $complexityDocs, $ruleId);
        }

        foreach ($this->ruleIdsForSection('naming') as $ruleId) {
            $this->assertStringContainsString($ruleId, $namingDocs, $ruleId);
        }
    }

    /**
     * @return list<string>
     */
    private function ruleIdsForSection(string $section): array
    {
        $ids = [];

        foreach (RuleCatalog::definitions() as $id => $definition) {
            if ($definition['section'] === $section) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function readProjectFile(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/'.$path);

        $this->assertIsString($contents, $path);

        /** Windows checkouts use CRLF; normalize so parsed shapes match expectations. */
        return str_replace("\r\n", "\n", $contents);
    }
}
