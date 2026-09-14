<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Rules\Usage\UselessVariableAliasAssignment;
use PHPUnit\Framework\TestCase;

final class UselessVariableAliasAssignmentTest extends TestCase
{
    use ParsesPhpSnippets;

    public function testCreatesAliasAssignmentFromDirectVariableCopy(): void
    {
        $assignment = UselessVariableAliasAssignment::fromNode($this->firstStatement('$alias = $source;'));

        $this->assertNotNull($assignment);
        $this->assertSame('alias', $assignment->variable);
        $this->assertSame('source', $assignment->source);
        $this->assertSame([
            'line'     => 1,
            'variable' => '$alias',
            'source'   => '$source',
        ], $assignment->toViolationRow());
    }

    public function testRejectsNonAliasAssignments(): void
    {
        $this->assertNull(UselessVariableAliasAssignment::fromNode($this->firstStatement('$alias = trim($source);')));
        $this->assertNull(UselessVariableAliasAssignment::fromNode($this->firstStatement('$alias = 1;')));
        $this->assertNull(UselessVariableAliasAssignment::fromNode($this->firstStatement('return $alias;')));
    }
}
