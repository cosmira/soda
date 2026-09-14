<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Reporting\Violation;
use PHPUnit\Framework\TestCase;

final class ViolationTest extends TestCase
{
    public function testPreservesNamedArgumentsInJson(): void
    {
        $violation = new Violation(rule: 'no_assignment_in_condition', file: '/path/to/file.php', value: 1, threshold: 0, method: 'run', class: 'App\Foo', line: 42, message: 'Assignment inside condition is forbidden.');

        $this->assertSame('App\Foo', $violation->class);
        $this->assertSame('run', $violation->method);
        $this->assertSame(42, $violation->line);
        $this->assertSame(['value' => 1, 'threshold' => 0], ['value' => $violation->value, 'threshold' => $violation->threshold]);
        $this->assertSame([
            'rule'      => 'no_assignment_in_condition',
            'file'      => '/path/to/file.php',
            'method'    => 'run',
            'class'     => 'App\Foo',
            'line'      => 42,
            'value'     => 1,
            'threshold' => 0,
            'message'   => 'Assignment inside condition is forbidden.',
        ], $violation->toArray());
    }

    public function testBuildsViolationWithNullMetadataDefaults(): void
    {
        $violation = new Violation(rule: 'max_line_length', file: '/path/to/file.php', value: 121, threshold: 120);

        $this->assertNull($violation->class);
        $this->assertNull($violation->method);
        $this->assertNull($violation->line);
        $this->assertSame(['value' => 121, 'threshold' => 120], ['value' => $violation->value, 'threshold' => $violation->threshold]);
        $this->assertSame([
            'rule'      => 'max_line_length',
            'file'      => '/path/to/file.php',
            'method'    => null,
            'class'     => null,
            'line'      => null,
            'value'     => 121,
            'threshold' => 120,
            'message'   => null,
        ], $violation->toArray());
    }
}
