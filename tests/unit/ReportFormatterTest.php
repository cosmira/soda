<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Reporting\QualityResult;
use Cosmira\Soda\Reporting\ReportFormatter;
use Cosmira\Soda\Reporting\RuleMetadata;
use Cosmira\Soda\Reporting\Violation;
use Illuminate\Console\OutputStyle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class ReportFormatterTest extends TestCase
{
    public function testFormatsPassingReportWithoutViolationGroups(): void
    {
        $text = $this->formatViolations([]);

        $this->assertStringContainsString('Soda Quality', $text);
        $this->assertStringContainsString('[OK] No issues', $text);
        $this->assertStringNotContainsString('[FAIL]', $text);
        $this->assertStringNotContainsString('issue', str_replace('No issues', '', $text));
        $this->assertStringNotContainsString('src/', $text);
    }

    public function testFormatsUnknownRuleWithMetadataFallback(): void
    {
        $violation = new Violation(rule: 'custom_rule', file: '/project/src/File.php', value: 12, threshold: 10, line: 7);

        $text = $this->formatViolation($violation, new RuleMetadata([]));

        $this->assertStringContainsString('src/File.php', $text);
        $this->assertStringContainsString('Line 7', $text);
        $this->assertStringContainsString('Unknown:', $text);
        $this->assertStringContainsString('12 (max 10)', $text);
        $this->assertStringContainsString('1 issue', $text);
        $this->assertStringContainsString('[FAIL] 1 issue', $text);
        $this->assertStringNotContainsString('2 issues', $text);
    }

    public function testFormatsViolationMessageInsteadOfThresholdDetail(): void
    {
        $violation = new Violation(rule: 'max_line_length', file: '/project/src/File.php', value: 121, threshold: 120, line: 9, message: 'Line is too long for this context.');

        $text = $this->formatViolation($violation);

        $this->assertStringContainsString('Line 9', $text);
        $this->assertStringContainsString('Line is too long for this context.', $text);
        $this->assertStringNotContainsString('Line length:', $text);
        $this->assertStringNotContainsString('121 (max 120)', $text);
        $this->assertStringContainsString('Name the intermediate value or wrap the expression', $text);
    }

    public function testExplainsHowToReduceTooManyArguments(): void
    {
        $violation = new Violation(rule: 'max_arguments', file: '/project/src/Service.php', value: 6, threshold: 5, method: 'App\\Service::__construct');

        $text = $this->formatViolation($violation);

        $this->assertStringContainsString('Arguments: 6 (max 5)', $text);
        $this->assertStringContainsString(
            'Move behavior to the owner of its data. Use a value object when related arguments share meaning, operations or an invariant; avoid a wrapper solely to reduce this count.',
            $text,
        );
    }

    public function testFormatsNamespaceNameLengthWithNamingMetadata(): void
    {
        $violation = new Violation(rule: 'namespace_name_length', file: '/project/src/File.php', value: 1, threshold: 3, line: 3);

        $text = $this->formatViolation($violation);

        $this->assertStringContainsString('Line 3', $text);
        $this->assertStringContainsString('Namespace name length:', $text);
        $this->assertStringContainsString('1 (max 3)', $text);
        $this->assertStringNotContainsString('Unknown:', $text);
    }

    public function testEscapesUserControlledReportText(): void
    {
        $violation = new Violation(rule: 'max_line_length', file: '/project/src/<error>File.php', value: 121, threshold: 120, method: 'App\Example::<error>run', message: 'Avoid <error>markup</error> in report text.');

        $text = $this->formatViolation($violation, decorated: true);

        $this->assertStringContainsString('src/<error>File.php', $text);
        $this->assertStringContainsString('App\Example::<error>run', $text);
        $this->assertStringContainsString('Avoid <error>markup</error> in report text.', $text);
        $this->assertStringNotContainsString("\033[37;41mFile.php", $text);
        $this->assertStringNotContainsString("\033[37;41mmarkup", $text);
    }

    public function testGroupsViolationsBySortedFileAndKeepsOrderWithinFile(): void
    {
        $text = $this->formatViolations([
            new Violation(rule: 'max_line_length', file: '/project/src/B.php', value: 121, threshold: 120, line: 20, message: 'First B finding.'),
            new Violation(rule: 'max_line_length', file: '/project/src/A.php', value: 121, threshold: 120, line: 10, message: 'A finding.'),
            new Violation(rule: 'max_line_length', file: '/project/src/B.php', value: 130, threshold: 120, line: 30, message: 'Second B finding.'),
        ]);

        $aFile = strpos($text, 'src/A.php');
        $bFile = strpos($text, 'src/B.php');
        $firstB = strpos($text, 'First B finding.');
        $secondB = strpos($text, 'Second B finding.');

        $this->assertIsInt($aFile);
        $this->assertIsInt($bFile);
        $this->assertIsInt($firstB);
        $this->assertIsInt($secondB);
        $this->assertLessThan($bFile, $aFile);
        $this->assertLessThan($secondB, $firstB);
        $this->assertStringContainsString('3 issues', $text);
        $this->assertStringContainsString('[FAIL] 3 issues', $text);
        $this->assertStringNotContainsString('1 issue', $text);
    }

    private function formatViolation(Violation $violation, ?RuleMetadata $metadata = null, bool $decorated = false): string
    {
        return $this->formatViolations([$violation], $metadata, $decorated);
    }

    /**
     * @param list<Violation> $violations
     */
    private function formatViolations(array $violations, ?RuleMetadata $metadata = null, bool $decorated = false): string
    {
        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, $decorated);

        (new ReportFormatter($metadata ?? RuleMetadata::default()))->write(
            new OutputStyle(new ArrayInput([]), $output),
            new QualityResult($violations),
            '/project',
        );

        return $output->fetch();
    }
}
