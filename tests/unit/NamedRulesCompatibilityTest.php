<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Reporting\QualityJsonReportFormatter;
use Cosmira\Soda\Rules\Check;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\FileIterator\Facade;

final class NamedRulesCompatibilityTest extends TestCase
{
    public function testFullAndSelectiveCollectorsProduceIdenticalDiagnostics(): void
    {
        $files = (new Facade)->getFilesAsArray([dirname(__DIR__, 2).'/demo-app'], ['.php']);
        $selective = (new Runner)->check($files, Soda::configure()->with(RuleCatalog::standard()));
        $allFacts = new class extends Check
        {
            public function id(): string
            {
                return 'request_all_facts';
            }
        };
        $full = (new Runner)->check($files, Soda::configure()->with([...RuleCatalog::standard(), $allFacts]));
        self::assertEquals($selective->violations->all(), $full->violations->all());
    }

    public function testEveryDemoDiagnosticMatchesTheReviewedStrictCatalogResult(): void
    {
        $root = dirname(__DIR__, 2);
        $files = (new Facade)->getFilesAsArray([$root.'/demo-app'], ['.php']);
        $result = (new Runner)->check($files, Soda::configure()->with(RuleCatalog::standard()));
        $report = (new QualityJsonReportFormatter)->format($result);
        $actual = $report['violations'];
        foreach ($actual as &$row) {
            $row['file'] = substr($row['file'], strlen($root) + 1);
        }
        unset($row);
        $expected = json_decode(file_get_contents($root.'/tests/_expectations/demo-violations.json'), true, flags: JSON_THROW_ON_ERROR);
        $sort = static function (array $rows): array {
            foreach ($rows as &$row) {
                ksort($row);
            }
            unset($row);
            usort($rows, static fn ($left, $right) => json_encode($left) <=> json_encode($right));

            return $rows;
        };
        self::assertCount(198, $expected);
        self::assertSame($sort($expected), $sort($actual));
    }
}
