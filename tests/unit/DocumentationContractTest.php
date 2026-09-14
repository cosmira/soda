<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Config\RuleCatalog;
use PHPUnit\Framework\TestCase;

final class DocumentationContractTest extends TestCase
{
    public function testExpertRuleProposalMatrixKeepsRequiredShapeAndExperts(): void
    {
        $matrix = $this->readProjectFile('docs/EXPERT_RULE_PROPOSALS.md');

        $this->assertExpertMatrixHeader($matrix);
        $this->assertExpertMatrixMentionsItsTestContract($matrix);
        $this->assertRequiredExpertsArePresent($matrix);
        $this->assertExpertMatrixRowsAreComplete($matrix);
    }

    private function assertExpertMatrixHeader(string $matrix): void
    {
        $this->assertStringContainsString(
            '| Expert | Problem | Code evidence | Rule proposal | How the rule should work |',
            $matrix,
        );
    }

    private function assertExpertMatrixMentionsItsTestContract(string $matrix): void
    {
        $this->assertStringContainsString('`DocumentationContractTest` protects this table shape', $matrix);
    }

    private function assertRequiredExpertsArePresent(string $matrix): void
    {
        foreach ($this->requiredExperts() as $expert) {
            $this->assertMatchesRegularExpression('/^\| '.preg_quote($expert, '/').' \|/m', $matrix, $expert);
        }
    }

    private function assertExpertMatrixRowsAreComplete(string $matrix): void
    {
        foreach ($this->expertMatrixRows($matrix) as $lineNumber => $cells) {
            $this->assertCount(5, $cells, 'Line '.$lineNumber);
            $this->assertContains($cells[0], $this->requiredExperts(), 'Line '.$lineNumber.' expert');

            foreach ($cells as $cell) {
                $this->assertNotSame('', $cell, 'Line '.$lineNumber);
            }

            $this->assertStringContainsString('`', $cells[2], 'Line '.$lineNumber.' evidence');
            $this->assertMatchesRegularExpression($this->proposalActionPattern(), $cells[3], 'Line '.$lineNumber.' proposal');
            $this->assertMatchesRegularExpression($this->behaviorActionPattern(), $cells[4], 'Line '.$lineNumber.' behavior');
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
        $this->assertStringNotContainsString('soda analyse', $reportDocs);
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

        return $contents;
    }

    /**
     * @return list<string>
     */
    private function requiredExperts(): array
    {
        return [
            'David Heinemeier Hansson',
            'Taylor Otwell',
            'Егор Бугаенко',
            'Fabien Potencier',
            'Jordi Boggiano',
            'Martin Fowler',
        ];
    }

    /**
     * @return array<int, list<string>>
     */
    private function expertMatrixRows(string $matrix): array
    {
        $rows = [];

        foreach (explode("\n", $matrix) as $index => $line) {
            if (! str_starts_with($line, '| ') || str_starts_with($line, '| ---')) {
                continue;
            }

            if (str_starts_with($line, '| Expert |')) {
                continue;
            }

            $rows[$index + 1] = array_map('trim', explode('|', trim($line, '|')));
        }

        return $rows;
    }

    private function proposalActionPattern(): string
    {
        return '/\b(rules?|coverage|tests?|documents?|metadata|config|init|list-rules|parser|forbids?|requires?|prefers?|includes?|validates?|asserts?|assertions?)\b/i';
    }

    private function behaviorActionPattern(): string
    {
        return '/\b(should|must|asserts?|tests?|detects?|reports?|rejects?|accepts?|requires?|parses?|serializes?|emits?|returns?|compares?|validates?|forbids?|preserves?|skips?|ignores?|loads?|includes?|provides?)\b/i';
    }
}
