<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class SodaRuleTest extends TestCase
{
    public function testDefaultStagesAreEmptyAndCustomRulesRequestAllFacts(): void
    {
        $rule = new class extends Check
        {
            public function id(): string
            {
                return 'custom';
            }
        };
        self::assertSame([], $rule->checkFile(new FileFacts('/file.php', '', [], [])));
        self::assertSame([], $rule->checkProject(new ProjectFacts));
        self::assertNull($rule->requiredAnalyses());
    }

    public function testFileChecksShareSourceAndAstAndProjectChecksRunLast(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'soda-check');
        file_put_contents($file, '<?php function work(int $a): int { return $a; }');
        $first = new class extends Check
        {
            public array $seen = [];

            public function id(): string
            {
                return 'capture';
            }

            public function checkFile(FileFacts $file): iterable
            {
                $this->seen[] = $file;

                return [];
            }

            public function checkProject(ProjectFacts $project): iterable
            {
                if (count($this->seen) !== 1) {
                    throw new \LogicException('File stage must finish first');
                }

                return [];
            }
        };
        $second = clone $first;

        try {
            $result = (new Runner)->check([$file, $file], Soda::configure()->with([$first, $second]));
            self::assertTrue($result->isPassing());
            self::assertCount(1, $first->seen);
            self::assertSame($first->seen[0], $second->seen[0]);
            self::assertSame(file_get_contents($file), $first->seen[0]->source);
            self::assertNotEmpty($first->seen[0]->nodes);
            self::assertSame(1, $first->seen[0]->metrics['methods']['work']['complexity']);
        } finally {
            unlink($file);
        }
    }

    public function testCustomPhpRuleReportsItsRealMeasurement(): void
    {
        $rule = new class extends Check
        {
            public function id(): string
            {
                return 'debug_calls';
            }

            public function requiredAnalyses(): ?array
            {
                return [];
            }

            public function checkFile(FileFacts $file): iterable
            {
                $count = substr_count($file->source, 'var_dump(');
                if ($count > 0) {
                    yield new Violation(rule: $this->id(), file: $file->path, value: $count, threshold: 0);
                }
            }
        };
        $found = iterator_to_array($rule->checkFile(new FileFacts('/file.php', 'var_dump(1); var_dump(2);', [], [])));
        self::assertSame(2, $found[0]->value);
        self::assertSame(0, $found[0]->threshold);
    }

    public function testProjectFactsReleaseAstAndSource(): void
    {
        $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse('<?php class Example {}');
        $reference = \WeakReference::create($nodes[0]);
        $file = new FileFacts('/file.php', '<?php class Example {}', $nodes, ['classes' => [], 'methods' => [], 'namespaces' => []]);
        $project = new ProjectFacts;
        $project->add($file);
        unset($nodes, $file);
        gc_collect_cycles();
        self::assertNull($reference->get());
        self::assertArrayNotHasKey('nodes', $project->files['/file.php']);
        self::assertArrayNotHasKey('source', $project->files['/file.php']);
    }
}
