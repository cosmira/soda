<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Reporting\QualityResult;
use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class ProjectNamespaceCountsTest extends TestCase
{
    public function testAggregatesCountsAcrossFilesForSameNamespace(): void
    {
        $engine = CheckFixture::selected(CheckFixture::checks(['max_classes_per_namespace' => 1], []), [null]);
        $result = new QualityResult(CheckFixture::runInput($engine, [[
            '/src/One.php'   => ['namespaces' => ['App\Services' => 2]],
            '/src/Two.php'   => ['namespaces' => ['App\Services' => 3]],
            '/src/Three.php' => ['namespaces' => ['App\Http' => 2]],
        ], []]));

        $violations = $result->violations->all();
        $this->assertCount(2, $violations);
        $this->assertSame('/src/One.php', $violations[0]->file);
        $this->assertSame(5, ['value' => $violations[0]->value, 'threshold' => $violations[0]->threshold]['value']);
        $this->assertSame('/src/Three.php', $violations[1]->file);
        $this->assertSame(2, ['value' => $violations[1]->value, 'threshold' => $violations[1]->threshold]['value']);
    }
}
