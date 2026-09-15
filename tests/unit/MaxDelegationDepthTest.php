<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\FactCollector;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Rules\Structure\MaxDelegationDepth;
use PHPUnit\Framework\TestCase;

final class MaxDelegationDepthTest extends TestCase
{
    public function testCrossFileForwardingChainAndThreshold(): void
    {
        $sources = [
            '<?php namespace App; class First { function __construct(private Second $next) {} function run($value) { return $this->next->send(value: $value); } }',
            '<?php namespace App; class Second { function __construct(private Third $next) {} function send($value) { return $this->next->write($value); } }',
            '<?php namespace App; class Third { function __construct(private Terminal $next) {} function write($value) { return $this->next->finish($value); } }',
            '<?php namespace App; class Terminal { function finish($value) { return trim($value); } }',
        ];
        self::assertSame([], $this->findings($sources, 3));
        $findings = $this->findings($sources, 2);
        self::assertCount(1, $findings);
        self::assertSame(3, $findings[0]->value);
        self::assertSame(2, $findings[0]->threshold);
        self::assertSame('App\\First', $findings[0]->class);
        self::assertSame('run', $findings[0]->method);
        self::assertSame(1, $findings[0]->line);
        self::assertStringContainsString('app\\first::run -> app\\second::send -> app\\third::write', $findings[0]->message);
    }

    public function testLocalPrivateMethodsAndStaticCallsCannotHideDepth(): void
    {
        $findings = $this->findings(['<?php class Workflow {
            function run($x) { return $this->second($x); }
            private function second($x) { return self::third($x); }
            private static function third($x) { return Terminal::run($x); }
        }'], 2);
        self::assertCount(1, $findings);
        self::assertSame(3, $findings[0]->value);
    }

    public function testAnOperationOrTransformationEndsTheTransparentChain(): void
    {
        self::assertSame([], $this->findings(['<?php class Workflow {
            function run($x) { authorize(); return $this->second($x); }
            function second($x) { return $this->third(trim($x)); }
            function third($x) { return Terminal::run($x); }
        }'], 1));
    }

    public function testForwardingCyclesAreAlwaysRejectedAndDeduplicated(): void
    {
        $findings = $this->findings(['<?php class Looping {
            function first($x) { return $this->second($x); }
            function second($x) { return $this->first($x); }
        }'], 10);
        self::assertCount(1, $findings);
        self::assertSame(2, $findings[0]->value);
        self::assertSame(0, $findings[0]->threshold);
        self::assertStringContainsString('cycle', $findings[0]->message);
    }

    public function testTraitsInheritanceAndLocalAliasesCannotHideTheChain(): void
    {
        $findings = $this->findings([
            '<?php trait Step { function run($x) { $alias = $x; $result = $this->Next->send($alias); return $result; } }',
            '<?php abstract class Base { use Step; function __construct(protected Second $Next) {} } class First extends Base {}',
            '<?php class Second { function send($x) { $alias = $x; return FinalStep::finish($alias); } }',
        ], 1);
        $classes = array_column($findings, 'class');
        self::assertContains('First', $classes);
        foreach ($findings as $finding) {
            self::assertSame(2, $finding->value);
        }
    }

    public function testPositiveLimitIsRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MaxDelegationDepth(0);
    }

    private function findings(array $sources, int $limit): array
    {
        $rule = new MaxDelegationDepth($limit);
        $project = new ProjectFacts;
        foreach ($sources as $source) {
            $path = tempnam(sys_get_temp_dir(), 'soda-delegation-');
            file_put_contents($path, $source);

            try {
                $project->add((new FactCollector)->collect($path, $rule->requiredAnalyses()));
            } finally {
                unlink($path);
            }
        }

        return iterator_to_array($rule->checkProject($project));
    }
}
