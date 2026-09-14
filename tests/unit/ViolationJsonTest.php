<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Reporting\Violation;
use PHPUnit\Framework\TestCase;

final class ViolationJsonTest extends TestCase
{
    public function testBuildsViolationWithCompleteMetadata(): void
    {
        $violation = new Violation(rule: 'methods_follow_call_order', file: 'src/App/Service.php', value: 3, threshold: 0, line: 42, class: 'App\Service', method: 'run', message: 'Method call order is surprising.');

        $this->assertSame([
            'rule'      => 'methods_follow_call_order',
            'file'      => 'src/App/Service.php',
            'method'    => 'run',
            'class'     => 'App\Service',
            'line'      => 42,
            'value'     => 3,
            'threshold' => 0,
            'message'   => 'Method call order is surprising.',
        ], $violation->toArray());
    }

    public function testBuildsViolationWithNullOptionalMetadata(): void
    {
        $violation = new Violation(rule: 'no_empty_catch_blocks', file: 'src/App/Service.php', value: 1, threshold: 0);

        $this->assertSame([
            'rule'      => 'no_empty_catch_blocks',
            'file'      => 'src/App/Service.php',
            'method'    => null,
            'class'     => null,
            'line'      => null,
            'value'     => 1,
            'threshold' => 0,
            'message'   => null,
        ], $violation->toArray());
    }
}
