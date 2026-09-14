<?php

declare(strict_types=1);

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Research\MaxDisconnectedMethodGroups;
use Cosmira\Soda\Rules\Check;
use Cosmira\Soda\Rules\Complexity\MaxCognitiveComplexity;
use Cosmira\Soda\Rules\Complexity\NoEmptyFinallyBlocks;
use Cosmira\Soda\Rules\Complexity\NoRedundantRethrow;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/MaxDisconnectedMethodGroups.php';

$manifest = json_decode(file_get_contents(__DIR__.'/corpus.json'), true, flags: JSON_THROW_ON_ERROR);
$root = $argv[1] ?? '/tmp/soda-corpus';
$output = $argv[2] ?? '/tmp/soda-corpus-results';
$partition = $argv[3] ?? 'discovery';
if (! in_array($partition, ['discovery', 'held-out'], true)) {
    throw new InvalidArgumentException('Choose discovery or held-out.');
}
@mkdir($output, 0777, true);

foreach ($manifest['projects'] as $spec) {
    if ($spec['partition'] !== $partition) {
        continue;
    }
    $directory = $root.'/'.$spec['name'];
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory.'/'.$spec['root'], FilesystemIterator::SKIP_DOTS)) as $file) {
        $relative = substr($file->getPathname(), strlen($directory) + 1);
        if ($file->isFile() && $file->getExtension() === 'php' && ! preg_match('~(^|/)(Tests|tests|Resources|vendor)(/|$)~', $relative)) {
            $files[] = $file->getPathname();
        }
    }
    sort($files);
    $evidence = new class extends Check
    {
        public array $callables = [];

        public array $types = [];

        public function id(): string
        {
            return 'research_evidence';
        }

        public function requiredAnalyses(): array
        {
            return ['cognitive', 'cohesion'];
        }

        public function checkFile(FileFacts $file): iterable
        {
            foreach ([...$file->metrics['methods'], ...($file->metrics['cognitiveExtras'] ?? [])] as $name => $row) {
                $this->callables[] = ['file' => $file->path, 'name' => $name, 'line' => $row['line'], 'score' => $row['cognitive_complexity']];
            }

            return [];
        }

        public function checkProject(ProjectFacts $project): iterable
        {
            foreach ($project->files as $path => $file) {
                foreach ($file['cohesion'] ?? [] as $name => $type) {
                    $this->types[] = ['file' => $path, 'name' => $name, 'line' => $type['line'], 'kind' => $type['kind'],
                        'declared'           => $type['declared']['metrics'], 'composed' => $type['composed']['metrics'],
                        'groups'             => $type['composed']['groups'], 'unknown' => $type['composed']['unknown']];
                }
            }

            return [];
        }
    };
    $limits = $manifest['candidate_limits'];
    $config = Soda::configure()->with([
        new MaxCognitiveComplexity($limits['max_cognitive_complexity']),
        new MaxDisconnectedMethodGroups($limits['max_disconnected_method_groups'], $limits['minimum_cohesion_methods']),
        new NoRedundantRethrow,
        new NoEmptyFinallyBlocks,
        $evidence,
    ]);
    memory_reset_peak_usage();
    $started = hrtime(true);
    $result = (new Runner)->check($files, $config);
    $seconds = (hrtime(true) - $started) / 1e9;
    $peak = memory_get_peak_usage(true);
    $violations = $result->violations->map(fn ($violation) => $violation->toArray())->all();
    $relative = function (array $row) use ($directory): array {
        $row['file'] = substr($row['file'], strlen($directory) + 1);

        return $row;
    };
    $report = ['project' => $spec, 'files' => count($files), 'seconds' => $seconds, 'peak_bytes' => $peak,
        'violations'     => array_map($relative, $violations),
        'callables'      => array_map($relative, $evidence->callables),
        'types'          => array_map($relative, $evidence->types)];
    file_put_contents($output.'/'.$spec['name'].'.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    printf("%s: %d files, %d callables, %d types, %d findings; %.3fs; %.1f MiB\n", $spec['name'], count($files), count($evidence->callables), count($evidence->types), count($violations), $seconds, $peak / 1048576);
    unset($result, $evidence, $config, $report);
}
