<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\CallableMetricsVisitor;
use Cosmira\Soda\Tests\ParsesPhpSnippets;
use PHPUnit\Framework\TestCase;

final class CallableEmptyCatchesTest extends TestCase
{
    use ParsesPhpSnippets;

    private function parseAndCollect(string $code): array
    {
        $visitor = new CallableMetricsVisitor();
        $this->traversePhpFile($code, $visitor);

        return $visitor->emptyCatches();
    }

    public function testCollectsEmptyCatchWithMethodContext(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Worker {
    public function run(): void {
        try {} catch (\Throwable $e) {}
    }
}
PHP;

        $result = $this->parseAndCollect($code);

        $this->assertCount(1, $result);
        $this->assertSame(5, $result[0]['line']);
        $this->assertSame('App\Worker', $result[0]['class']);
        $this->assertSame('App\Worker::run', $result[0]['method']);
    }

    public function testIgnoresCatchBlocksWithBody(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Worker {
    public function run(): void {
        try {} catch (\Throwable $e) { report($e); }
    }
}
PHP;

        $this->assertSame([], $this->parseAndCollect($code));
    }

    public function testAnonymousClassEmptyCatchDoesNotLeakIntoOuterMethod(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Worker {
    public function first(): object {
        return new class {
            public function ignored(): void {
                try {} catch (\Throwable $e) {}
            }
        };
    }
}
PHP;

        $this->assertSame([], $this->parseAndCollect($code));
    }
}
