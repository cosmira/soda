<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Reporting\QualityJsonReportFormatter;
use PHPUnit\Framework\TestCase;

final class SandboxAuditCorpusTest extends TestCase
{
    public function testReviewCoversEveryIncomingFinding(): void
    {
        $sources = $this->read('sources.json');
        $findings = $this->read('findings.json')['findings'];
        $hashes = array_column($sources['files'], 'sha256', 'path');
        self::assertCount($sources['after'], $findings);
        self::assertSame($sources['before'], count($findings) + count($this->read('resolved.json')) + count($this->read('policy-changes.json')));
        $ids = [];
        foreach ($findings as $finding) {
            self::assertArrayNotHasKey($finding['id'], $ids);
            $ids[$finding['id']] = true;
            self::assertSame('confirmed_policy_violation', $finding['review']['status']);
            self::assertSame($hashes[$finding['diagnostic']['file']], $finding['review']['evidence']['source_sha256']);
            self::assertNotEmpty($finding['review']['evidence']['observation']);
        }
    }

    public function testInstalledSandboxMatchesTheReviewedSnapshot(): void
    {
        $location = getenv('SODA_SANDBOX_ROOT');
        if ($location === false || $location === '') {
            self::markTestSkipped('Set SODA_SANDBOX_ROOT to the Sandbox checkout for the complete corpus check.');
        }
        $root = realpath($location);
        self::assertNotFalse($root);
        $sources = $this->read('sources.json');
        $hashes = [];
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/src', \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
                $hashes[substr($file->getPathname(), strlen($root) + 1)] = hash_file('sha256', $file->getPathname());
            }
        }
        ksort($hashes);
        self::assertSame(array_column($sources['files'], 'sha256', 'path'), $hashes, 'Source changed: review required before accepting diagnostics.');
        $framework = $sources['framework'];
        self::assertSame($framework['sha256'], hash_file('sha256', $root.'/vendor/laravel/framework/'.$framework['file']));
        sort($files);
        $result = (new Runner)->check($files, Soda::configure()->with(RuleCatalog::standard()));
        $actual = (new QualityJsonReportFormatter)->format($result)['violations'];
        foreach ($actual as &$diagnostic) {
            $diagnostic['file'] = substr($diagnostic['file'], strlen($root) + 1);
        }
        unset($diagnostic);
        $expected = array_column($this->read('findings.json')['findings'], 'diagnostic');
        self::assertSame($this->canonical($expected), $this->canonical($actual));
    }

    private function canonical(array $diagnostics): array
    {
        $rows = [];
        foreach ($diagnostics as $diagnostic) {
            ksort($diagnostic);
            $rows[] = json_encode($diagnostic, JSON_THROW_ON_ERROR);
        }
        sort($rows);

        return $rows;
    }

    private function read(string $file): array
    {
        return json_decode(file_get_contents(__DIR__.'/../../docs/research/sandbox-audit/'.$file), true, flags: JSON_THROW_ON_ERROR);
    }
}
