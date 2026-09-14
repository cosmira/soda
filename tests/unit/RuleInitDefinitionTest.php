<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Rules\Naming\MaxVariableNameWords;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RuleInitDefinitionTest extends TestCase
{
    public function testRejectsUnsupportedConstructorType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('maxWords must be at least 1.');

        new MaxVariableNameWords(0);
    }
}
