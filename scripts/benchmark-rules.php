<?php

declare(strict_types=1);

use Cosmira\Soda\Analysis\FactCollector;
use Cosmira\Soda\Rules\Complexity\MaxCyclomaticComplexity;

require __DIR__.'/../vendor/autoload.php';

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../src'));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $files[] = $file->getPathname();
    }
}
sort($files);
$rule = new MaxCyclomaticComplexity(3);
$measurements = ['full' => [], 'selected' => []];
$firstPass = [];
$fingerprint = static fn (): string => hash('sha256', implode('', array_map(
    static fn (string $file): string => hash_file('sha256', $file),
    $files,
)));
$corpus = $fingerprint();
$expected = null;
for ($run = 0; $run < 5; $run++) {
    // Alternate execution order; omit the first run to warm both paths.
    foreach ($run % 2 === 0 ? ['full', 'selected'] : ['selected', 'full'] as $mode) {
        gc_collect_cycles();
        $extractor = new FactCollector;
        $violations = [];
        $start = hrtime(true);
        foreach ($files as $file) {
            $facts = $extractor->collect($file, $mode === 'full' ? null : $rule->requiredAnalyses());
            foreach ($rule->checkFile($facts) as $violation) {
                $violations[] = $violation->toArray();
            }
        }
        $seconds = (hrtime(true) - $start) / 1e9;
        $expected ??= $violations;
        if ($violations !== $expected) {
            throw new RuntimeException('The analysis modes returned different violations.');
        }
        if ($run === 0) {
            $firstPass[$mode] = $seconds;
        }
        if ($run > 0) {
            $measurements[$mode][] = $seconds;
        }
    }
}
if ($fingerprint() !== $corpus) {
    throw new RuntimeException('Source files changed during the benchmark; rerun on a stable checkout.');
}
$median = static function (array $values): float {
    sort($values);

    return ($values[1] + $values[2]) / 2;
};
$full = $median($measurements['full']);
$selected = $median($measurements['selected']);
echo json_encode([
    'corpus_sha256'           => $corpus,
    'first_pass_seconds'      => $firstPass,
    'php'                     => PHP_VERSION,
    'files'                   => count($files),
    'rule'                    => MaxCyclomaticComplexity::class,
    'violations'              => count($expected),
    'identical_results'       => true,
    'seconds'                 => $measurements,
    'median_full_seconds'     => $full,
    'median_selected_seconds' => $selected,
    'speedup'                 => $full / $selected,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
