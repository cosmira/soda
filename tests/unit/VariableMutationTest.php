<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\Usage\VariableMutation;
use PHPUnit\Framework\TestCase;

final class VariableMutationTest extends TestCase
{
    use ParsesPhpSnippets;

    public function testMatchesDirectReassignment(): void
    {
        $this->assertTrue(VariableMutation::isMutationOf($this->firstExpression('$alias = 1;'), 'alias'));
    }

    public function testMatchesCompoundAssignment(): void
    {
        $this->assertTrue(VariableMutation::isMutationOf($this->firstExpression('$alias += 1;'), 'alias'));
    }

    public function testMatchesIncrementAndDecrement(): void
    {
        $this->assertTrue(VariableMutation::isMutationOf($this->firstExpression('$alias++;'), 'alias'));
        $this->assertTrue(VariableMutation::isMutationOf($this->firstExpression('--$alias;'), 'alias'));
    }

    public function testRejectsOtherVariablesAndReads(): void
    {
        $this->assertFalse(VariableMutation::isMutationOf($this->firstExpression('$other = 1;'), 'alias'));
        $this->assertFalse(VariableMutation::isMutationOf($this->firstExpression('return $alias;'), 'alias'));
    }
}
