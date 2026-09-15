<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Structure\MethodsFollowCallOrder;
use Cosmira\Soda\Rules\Usage\NoNumericArrayIndex;
use PHPUnit\Framework\TestCase;

final class SimpleApiDiagnosticsTest extends TestCase
{
    public function testSimpleApiAndCounterexamplesThroughTheRunner(): void
    {
        $root = dirname(__DIR__).'/fixtures/design/';
        $config = Soda::configure()->with([new NoNumericArrayIndex, new MethodsFollowCallOrder]);
        $accepted = (new Runner)->check([$root.'simple-api.php'], $config);
        self::assertSame([], $accepted->violations->all());
        $review = (new Runner)->check([$root.'reviewable-api.php'], $config);
        $rules = $review->violations->pluck('rule')->all();
        sort($rules);
        self::assertSame(['methods_follow_call_order', 'no_numeric_array_index'], $rules);
    }
}
