<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Contracts\Check;
use PHPUnit\Framework\TestCase;

final class CheckDefaultsTest extends TestCase
{
    public function testCustomAstChecksRequireNoAdditionalAnalysesByDefault(): void
    {
        $rule = new class extends Check
        {
            public function id(): string
            {
                return 'custom_ast_check';
            }
        };
        self::assertSame([], $rule->requiredAnalyses());
        self::assertSame('array', (string) (new \ReflectionMethod(Check::class, 'requiredAnalyses'))->getReturnType());
    }
}
