<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Rules\Usage\UnusedMethodAnalyser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UnusedMethodsTest extends TestCase
{
    use ParsesPhpSnippets;

    private UnusedMethodAnalyser $analyser;

    protected function setUp(): void
    {
        $this->analyser = new UnusedMethodAnalyser;
    }

    private function hasViolation(string $code): bool
    {
        return $this->analyser->analyse($this->parseSnippet($code)) !== [];
    }

    #[DataProvider('callForms')]
    public function testPreservesRecognizedCallForms(string $call, bool $unused): void
    {
        $code = 'class Example { private function target() {} public function run() { '.$call.'; } }';
        $this->assertSame($unused, $this->hasViolation($code));
    }

    public static function callForms(): iterable
    {
        yield 'instance' => ['$this->target()', false];
        yield 'other receiver preserves name policy' => ['$other->target()', false];
        yield 'relative static' => ['parent::target()', false];
        yield 'named static receiver remains outside policy' => ['Other::target()', true];
        yield 'callback array' => ['call_user_func_array([$this, "target"], [])', false];
        yield 'empty callback' => ['call_user_func()', true];
        yield 'short callback' => ['call_user_func([$this])', true];
        yield 'nonliteral callback' => ['call_user_func([$this, $method])', true];
        yield 'string callback remains outside policy' => ['call_user_func("target")', true];
        yield 'first class callback function' => ['call_user_func(...)', true];
        yield 'dynamic instance disables findings' => ['$this->$method()', false];
        yield 'nullsafe call remains outside policy' => ['$other?->target()', true];
    }

    // -------------------------------------------------------------------------
    // Spec — Cases that MUST produce a violation
    // -------------------------------------------------------------------------

    /** Case 1 — unused private method */
    public function testCase1UnusedPrivate(): void
    {
        $this->assertTrue($this->hasViolation(
            'class A { private function foo() {} }',
        ));
    }

    /** Unused protected with no supertype contract in file. */
    public function testCase2UnusedProtectedReported(): void
    {
        $this->assertTrue($this->hasViolation(
            'class A { protected function foo() {} }',
        ));
    }

    /** Private and uncontracted protected both reported. */
    public function testCase8MultipleUnused(): void
    {
        $violations = $this->analyser->analyse($this->parseSnippet(
            'class A { private function a() {} protected function b() {} }',
        ));

        $this->assertCount(2, $violations);
    }

    /** Abstract ancestor declares name → protected override not reported. */
    public function testProtectedSkippedWhenAbstractAncestorDeclaresMethod(): void
    {
        $this->assertFalse($this->hasViolation(
            'abstract class A { abstract protected function f(): void; } class B extends A { protected function f(): void {} }',
        ));
    }

    /** Child override is skipped; abstract parent stub may still be reported if unused in A. */
    public function testChildProtectedOverrideSkippedWhenAbstractDeclaresConcreteProtected(): void
    {
        $violations = $this->analyser->analyse($this->parseSnippet(
            'abstract class A { protected function f(): void {} } class B extends A { protected function f(): void {} }',
        ));

        $this->assertSame([], array_values(array_filter(
            $violations,
            static fn (array $v): bool => $v['class'] === 'B',
        )));
    }

    /**
     * Interface declares same name (parser accepts; invalid at runtime) → implementor protected skipped.
     */
    public function testProtectedSkippedWhenInterfaceDeclaresSameName(): void
    {
        $this->assertFalse($this->hasViolation(
            'interface I { protected function x(): void; } class C implements I { protected function x(): void {} }',
        ));
    }

    /** final protected on abstract class — shared API, not reported. */
    public function testFinalProtectedOnAbstractClassSkipped(): void
    {
        $this->assertFalse($this->hasViolation(
            'abstract class A { final protected function api(): void {} }',
        ));
    }

    /** #[\Override] when parent class is not in this compilation unit. */
    public function testProtectedSkippedWithOverrideAttribute(): void
    {
        $this->assertFalse($this->hasViolation(
            'class C extends P { #[\Override] protected function f(): void {} }',
        ));
    }

    /** Protected not on contract; other protected on implementor still reported. */
    public function testProtectedOrphanStillReportedWhenInterfaceDeclaresOtherMethod(): void
    {
        $violations = $this->analyser->analyse($this->parseSnippet(
            'interface I { public function run(): void; } class C implements I { public function run(): void {} protected function helper(): void {} }',
        ));

        $this->assertCount(1, $violations);
        $this->assertSame('helper', $violations[0]['method']);
        $this->assertSame('protected', $violations[0]['visibility']);
    }

    /** Case 9 — trait with unused private method */
    public function testCase9TraitUnused(): void
    {
        $this->assertTrue($this->hasViolation(
            'trait T { private function foo() {} } class A { use T; }',
        ));
    }

    // -------------------------------------------------------------------------
    // Spec — Cases that must NOT produce a violation
    // -------------------------------------------------------------------------

    /** Case 3 — private method called via $this->foo() */
    public function testCase3UsedPrivate(): void
    {
        $this->assertFalse($this->hasViolation(
            'class A { private function foo() {} public function run() { $this->foo(); } }',
        ));
    }

    /** Case 4 — protected method used in child class */
    public function testCase4UsedProtectedInChild(): void
    {
        $this->assertFalse($this->hasViolation(
            'class A { protected function foo() {} } class B extends A { public function run() { $this->foo(); } }',
        ));
    }

    /** Case 5 — magic method is never flagged */
    public function testCase5MagicMethod(): void
    {
        $this->assertFalse($this->hasViolation(
            'class A { private function __toString() { return ""; } }',
        ));
    }

    /** Case 6 — dynamic call ($this->$m()) treats all methods as potentially used */
    public function testCase6DynamicCall(): void
    {
        $this->assertFalse($this->hasViolation(
            'class A { private function foo() {} public function run() { $m = "foo"; $this->$m(); } }',
        ));
    }

    /** Case 7 — call_user_func([$this, 'foo']) counts as a call */
    public function testCase7CallUserFunc(): void
    {
        $this->assertFalse($this->hasViolation(
            'class A { private function foo() {} public function run() { call_user_func([$this, "foo"]); } }',
        ));
    }

    /** Case 10 — trait method called via $this in using class */
    public function testCase10TraitUsed(): void
    {
        $this->assertFalse($this->hasViolation(
            'trait T { private function foo() {} } class A { use T; public function run() { $this->foo(); } }',
        ));
    }

    // -------------------------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------------------------

    /** Static self:: call counts */
    public function testSelfStaticCall(): void
    {
        $this->assertFalse($this->hasViolation(
            'class A { private static function foo() {} public function run() { self::foo(); } }',
        ));
    }

    /** Static static:: call counts */
    public function testLateStaticBindingCall(): void
    {
        $this->assertFalse($this->hasViolation(
            'class A { private static function foo() {} public function run() { static::foo(); } }',
        ));
    }

    /** Public methods are never flagged */
    public function testPublicMethodIsNotFlagged(): void
    {
        $this->assertFalse($this->hasViolation(
            'class A { public function foo() {} }',
        ));
    }

    /** Abstract method is not flagged */
    public function testAbstractMethodIsNotFlagged(): void
    {
        $this->assertFalse($this->hasViolation(
            'abstract class A { abstract protected function foo(); }',
        ));
    }

    /** Analyser still sees private setUp; {@see NoUnusedMethods} merges DEFAULT_IGNORE for the rule. */
    public function testPrivateSetUpReportedByAnalyser(): void
    {
        $this->assertTrue($this->hasViolation(
            'class A { private function setUp() {} }',
        ));
    }

    /** Violation contains expected metadata */
    public function testViolationMetadata(): void
    {
        $violations = $this->analyser->analyse($this->parseSnippet(
            'class MyClass { private function myMethod() {} }',
        ));

        $this->assertCount(1, $violations);
        $this->assertSame('MyClass', $violations[0]['class']);
        $this->assertSame('myMethod', $violations[0]['method']);
        $this->assertSame('private', $violations[0]['visibility']);
        $this->assertIsInt($violations[0]['line']);
    }
}
