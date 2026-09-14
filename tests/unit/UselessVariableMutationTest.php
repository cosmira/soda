<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Rules\Usage\UselessVariableMutation;
use PHPUnit\Framework\TestCase;

final class UselessVariableMutationTest extends TestCase
{
    use ParsesPhpSnippets;

    public function testMatchesDirectReassignment(): void
    {
        $this->assertTrue(UselessVariableMutation::isMutationOf($this->firstExpression('$alias = 1;'), 'alias'));
    }

    public function testMatchesCompoundAssignment(): void
    {
        $this->assertTrue(UselessVariableMutation::isMutationOf($this->firstExpression('$alias += 1;'), 'alias'));
    }

    public function testMatchesIncrementAndDecrement(): void
    {
        $this->assertTrue(UselessVariableMutation::isMutationOf($this->firstExpression('$alias++;'), 'alias'));
        $this->assertTrue(UselessVariableMutation::isMutationOf($this->firstExpression('--$alias;'), 'alias'));
    }

    public function testRejectsOtherVariablesAndReads(): void
    {
        $this->assertFalse(UselessVariableMutation::isMutationOf($this->firstExpression('$other = 1;'), 'alias'));
        $this->assertFalse(UselessVariableMutation::isMutationOf($this->firstExpression('return $alias;'), 'alias'));
    }
}
