<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\FactCollector;
use PHPUnit\Framework\TestCase;

final class FactCollectionIntegrationTest extends TestCase
{
    public function testCollectsResolvedStructureAndMethodFacts(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'soda-collect-');
        file_put_contents($path, <<<'PHP'
<?php
namespace App;

final class Example
{
    public function __construct(private Logger $logger, private Client $client) {}

    public function run(bool $ready): int
    {
        if ($ready) {
            return 1;
        }

        return 0;
    }
}
PHP);

        try {
            $file = (new FactCollector)->collect($path);
        } finally {
            unlink($path);
        }

        $classes = $file->metrics['classes'];

        $this->assertArrayHasKey('App\Example', $classes);
        $this->assertSame(2, $classes['App\Example']['dependencies']);
        $this->assertSame('App', $classes['App\Example']['namespace']);
        $this->assertSame(1, $classes['App\Example']['namespace_depth']);
        $this->assertSame(2, $file->metrics['methods']['App\Example::run']['returns']);
        $this->assertSame(1, $file->metrics['methods']['App\Example::run']['boolean_conditions'][0]['count']);
        $this->assertSame(2, $classes['App\Example']['efferent_coupling']);
    }
}
