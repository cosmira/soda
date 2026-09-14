<?php

declare(strict_types=1);
use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Complexity\NoComplexControlConditions;
use Cosmira\Soda\Rules\Usage\NoUnusedMethods;

// Usage: php practical-php-corpus.php REPOSITORY SNAPSHOTS OUTPUT
// Analyze source only; never load or execute the corpus projects.
require $argv[1].'/vendor/autoload.php';
$specs = json_decode(file_get_contents($argv[1].'/docs/research/corpus.json'), true, flags: JSON_THROW_ON_ERROR);
if (! is_dir($argv[3])) {
    mkdir($argv[3], 0777, true);
}
foreach ($specs['projects'] as $spec) {
    $root = $argv[2].'/'.$spec['name'];
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$spec['root'], FilesystemIterator::SKIP_DOTS)) as $file) {
        $relative = substr($file->getPathname(), strlen($root) + 1);
        if ($file->isFile() && $file->getExtension() === 'php' && ! preg_match('~(^|/)(Tests|tests|Resources|vendor)(/|$)~', $relative)) {
            $files[] = $file->getPathname();
        }
    }
    sort($files);
    $config = Soda::configure()->with([new NoComplexControlConditions, new NoUnusedMethods]);
    $result = (new Runner)->check($files, $config);
    $rows = [];
    foreach ($result->violations as $violation) {
        $row = $violation->toArray();
        $row['file'] = substr($row['file'], strlen($root) + 1);
        $rows[] = $row;
    }
    file_put_contents($argv[3].'/'.$spec['name'].'.json', json_encode(['project' => $spec, 'files' => count($files), 'violations' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    echo $spec['name'].': '.count($rows)." findings\n";
}
