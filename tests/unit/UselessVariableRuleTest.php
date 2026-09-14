<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Rules\Usage\UselessVariableAnalyser;
use PHPUnit\Framework\TestCase;

final class UselessVariableRuleTest extends TestCase
{
    use ParsesPhpSnippets;

    private UselessVariableAnalyser $analyser;

    protected function setUp(): void
    {
        $this->analyser = new UselessVariableAnalyser;
    }

    /** @return list<array{line: int, variable: string, source: string}> */
    private function violations(string $code): array
    {
        return $this->analyser->analyse($this->parseSnippet($code));
    }

    private function hasViolation(string $code): bool
    {
        return $this->violations($code) !== [];
    }

    // -------------------------------------------------------------------------
    // Spec — Cases that MUST produce a violation (true)
    // -------------------------------------------------------------------------

    /** Case 1 — simple alias: $a = $b; return $a; */
    public function testCase1SimpleAlias(): void
    {
        $this->assertTrue($this->hasViolation(
            'function test($b) { $a = $b; return $a; }',
        ));
    }

    /** Case 2 — alias passed to a function: $a = $b; foo($a); */
    public function testCase2AliasInCall(): void
    {
        $this->assertTrue($this->hasViolation(
            'function test($b) { $a = $b; foo($a); }',
        ));
    }

    /** Case 3 — chain alias: $a = $b; $c = $a; return $c; */
    public function testCase3ChainAlias(): void
    {
        $this->assertTrue($this->hasViolation(
            'function test($b) { $a = $b; $c = $a; return $c; }',
        ));
    }

    // -------------------------------------------------------------------------
    // Spec — Cases that must NOT produce a violation (false)
    // -------------------------------------------------------------------------

    /** Case 4 — transformation: $a = trim($b) is not a direct copy */
    public function testCase4Transformation(): void
    {
        $this->assertFalse($this->hasViolation(
            'function test($b) { $a = trim($b); return $a; }',
        ));
    }

    /** Case 5 — mutation: $a++ after assignment */
    public function testCase5Mutation(): void
    {
        $this->assertFalse($this->hasViolation(
            'function test($b) { $a = $b; $a++; return $a; }',
        ));
    }

    /** Case 6 — reassignment: $a = 10 after $a = $b */
    public function testCase6Reassignment(): void
    {
        $this->assertFalse($this->hasViolation(
            'function test($b) { $a = $b; $a = 10; return $a; }',
        ));
    }

    /** Case 7 — passed by reference: foo(&$a) */
    public function testCase7Reference(): void
    {
        $this->assertFalse($this->hasViolation(
            'function test($b) { $a = $b; foo(&$a); }',
        ));
    }

    /** Case 8 — object mutation: $a->x = 1 */
    public function testCase8ObjectMutation(): void
    {
        $this->assertFalse($this->hasViolation(
            'function test($obj) { $a = $obj; $a->x = 1; }',
        ));
    }

    /** Case 9 — source unset: unset($b) before $a is used */
    public function testCase9UnsetSource(): void
    {
        $this->assertFalse($this->hasViolation(
            'function test($b) { $a = $b; unset($b); return $a; }',
        ));
    }

    /** Case 10 — closure capture: fn() => $a */
    public function testCase10Closure(): void
    {
        $this->assertFalse($this->hasViolation(
            'function test($b) { $a = $b; return fn() => $a; }',
        ));
    }

    // -------------------------------------------------------------------------
    // Violation metadata
    // -------------------------------------------------------------------------

    public function testViolationContainsLineVariableAndSource(): void
    {
        $violations = $this->violations(
            'function test($b) { $a = $b; return $a; }',
        );

        $this->assertCount(1, $violations);
        $this->assertSame('$a', $violations[0]['variable']);
        $this->assertSame('$b', $violations[0]['source']);
        $this->assertIsInt($violations[0]['line']);
        $this->assertGreaterThan(0, $violations[0]['line']);
    }

    // -------------------------------------------------------------------------
    // Scope behaviour
    // -------------------------------------------------------------------------

    /** Top-level code is not analysed — only function/method bodies. */
    public function testTopLevelCodeIsIgnored(): void
    {
        $this->assertFalse($this->hasViolation('$a = $b; return $a;'));
    }

    /** Class methods are analysed. */
    public function testClassMethodIsAnalysed(): void
    {
        $this->assertTrue($this->hasViolation(
            'class Foo { public function test($b) { $a = $b; return $a; } }',
        ));
    }

    /** Multiple useless variables in one scope are each reported. */
    public function testMultipleUselessVariablesInOneScope(): void
    {
        $violations = $this->violations(
            'function test($b) { $a = $b; $c = $b; return $a + $c; }',
        );

        $this->assertCount(2, $violations);
    }

    /** Regular closure use($a) prevents detection. */
    public function testRegularClosureCapturePreventsDetection(): void
    {
        $this->assertFalse($this->hasViolation(
            'function test($b) { $a = $b; $fn = function() use ($a) { return $a; }; return $fn; }',
        ));
    }

    /** Compound assignment ($a += 1) counts as mutation. */
    public function testCompoundAssignmentIsMutation(): void
    {
        $this->assertFalse($this->hasViolation(
            'function test($b) { $a = $b; $a += 1; return $a; }',
        ));
    }

    /** Variable never used after assignment — no violation (nothing to replace). */
    public function testUnusedVariableIsNotFlagged(): void
    {
        $this->assertFalse($this->hasViolation(
            'function test($b) { $a = $b; }',
        ));
    }
}
