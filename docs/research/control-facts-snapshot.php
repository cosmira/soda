<?php

declare(strict_types=1);
use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\NoComplexControlConditionsTest;
use Cosmira\Soda\Rules\Complexity\NoAssignmentInCondition;
use Cosmira\Soda\Rules\Complexity\NoComplexControlConditions;
use Cosmira\Soda\Rules\Complexity\NoElseBranches;

// Run from the repository root with development dependencies installed.
// Usage: php docs/research/control-facts-snapshot.php OUTPUT.json
// Compare outputs before/after a refactor; source fixtures are parsed, not executed.
require getcwd().'/vendor/autoload.php';
$files = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('tests', FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->isFile() && $file->getExtension() === 'php' && $file->getFilename() !== 'ExplicitBehaviorCandidatesTest.php') {
        $files[] = $file->getPathname();
    }
}
$directory = '/tmp/soda-control-fact-fixtures';
if (! is_dir($directory)) {
    mkdir($directory);
}
foreach (NoComplexControlConditionsTest::conditions() as $index => [$condition, $reason]) {
    $path = $directory.'/'.md5($index).'.php';
    file_put_contents($path, '<?php if ('.$condition.') {} elseif ($value = nextValue()) {} else {}');
    $files[] = $path;
}
sort($files);
$rules = [new NoComplexControlConditions, new NoElseBranches, new NoAssignmentInCondition];
$result = (new Runner)->check($files, Soda::configure()->with($rules));
file_put_contents($argv[1], json_encode(['files' => count($files), 'violations' => $result->violations->map(fn ($v) => $v->toArray())->all()], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
