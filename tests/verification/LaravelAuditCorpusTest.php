<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Reporting\QualityJsonReportFormatter;
use PHPUnit\Framework\TestCase;

final class LaravelAuditCorpusTest extends TestCase
{
    public function testEveryReviewedFindingBelongsToThePinnedSource(): void
    {
        $manifest = $this->read('packages.json');
        $sources = [];
        foreach ($manifest['packages'] as $package) {
            foreach ($package['files'] as $file) {
                $sources[$package['name']][$file['path']] = $file['sha256'];
            }
        }
        self::assertCount(6, $sources);
        $ids = [];
        foreach ($this->read('findings.json')['findings'] as $finding) {
            self::assertArrayNotHasKey($finding['id'], $ids);
            $ids[$finding['id']] = true;
            self::assertSame('confirmed_policy_violation', $finding['review']['status']);
            self::assertSame($sources[$finding['package']][$finding['diagnostic']['file']], $finding['review']['evidence']['source_sha256']);
        }
    }

    public function testInstalledCorpusMatchesEveryReviewedDiagnostic(): void
    {
        $mapping = getenv('SODA_LARAVEL_CORPUS');
        if ($mapping === false || $mapping === '') {
            self::markTestSkipped('Set SODA_LARAVEL_CORPUS to a JSON file mapping package names to installed package roots.');
        }
        $roots = json_decode(file_get_contents($mapping), true, flags: JSON_THROW_ON_ERROR);
        $ledger = $this->read('findings.json')['findings'];
        foreach ($this->read('packages.json')['packages'] as $package) {
            self::assertArrayHasKey($package['name'], $roots);
            $root = realpath($roots[$package['name']]);
            self::assertNotFalse($root);
            $files = [];
            $hashes = [];
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/src', \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $path = $file->getPathname();
                    $files[] = $path;
                    $hashes[substr($path, strlen($root) + 1)] = hash_file('sha256', $path);
                }
            }
            $expectedHashes = array_column($package['files'], 'sha256', 'path');
            ksort($hashes);
            ksort($expectedHashes);
            self::assertSame($expectedHashes, $hashes, $package['name'].' source snapshot changed; re-audit required');
            sort($files);
            $actual = [];
            $result = (new Runner)->check($files, Soda::configure()->with(RuleCatalog::standard()));
            foreach ((new QualityJsonReportFormatter)->format($result)['violations'] as $diagnostic) {
                $diagnostic['file'] = substr($diagnostic['file'], strlen($root) + 1);
                $actual[] = $this->canonical($diagnostic);
            }
            $expected = [];
            foreach ($ledger as $finding) {
                if ($finding['package'] === $package['name']) {
                    $expected[] = $this->canonical($finding['diagnostic']);
                }
            }
            sort($actual);
            sort($expected);
            self::assertSame($expected, $actual, $package['name'].' diagnostic multiset changed; review each difference');
        }
    }

    private function canonical(array $diagnostic): string
    {
        ksort($diagnostic);

        return json_encode($diagnostic, JSON_THROW_ON_ERROR);
    }

    private function read(string $name): array
    {
        return json_decode(file_get_contents(__DIR__.'/../../docs/research/laravel-audit/'.$name), true, flags: JSON_THROW_ON_ERROR);
    }
}
