<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\AnonymousClassBoundary;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PHPUnit\Framework\TestCase;

final class AnonymousClassBoundaryTest extends TestCase
{
    use ParsesPhpSnippets;

    public function testDetectsDirectMembersAndNestedExpressionsInsideAnonymousClass(): void
    {
        $nodes = $this->parseAnonymousClassSnippet();
        $finder = new NodeFinder();

        $method = $finder->findFirst($nodes, static fn (Node $node): bool => $node instanceof ClassMethod && $node->name->toString() === 'run');
        $const = $finder->findFirst($nodes, static fn (Node $node): bool => $node instanceof ClassConst);
        $call = $finder->findFirst($nodes, static fn (Node $node): bool => $node instanceof MethodCall);

        $this->assertInstanceOf(ClassMethod::class, $method);
        $this->assertInstanceOf(ClassConst::class, $const);
        $this->assertInstanceOf(MethodCall::class, $call);
        $this->assertTrue(AnonymousClassBoundary::isDirectMember($method));
        $this->assertTrue(AnonymousClassBoundary::isDirectMember($const));
        $this->assertTrue(AnonymousClassBoundary::isInside($call));
    }

    public function testRejectsNamedClassMembers(): void
    {
        $nodes = $this->parseWithParents('<?php class Service { public const TOKEN = "x"; public function run(): void { $this->call(); } }');
        $finder = new NodeFinder();

        $method = $finder->findFirst($nodes, static fn (Node $node): bool => $node instanceof ClassMethod);
        $const = $finder->findFirst($nodes, static fn (Node $node): bool => $node instanceof ClassConst);
        $call = $finder->findFirst($nodes, static fn (Node $node): bool => $node instanceof MethodCall);

        $this->assertInstanceOf(ClassMethod::class, $method);
        $this->assertInstanceOf(ClassConst::class, $const);
        $this->assertInstanceOf(MethodCall::class, $call);
        $this->assertFalse(AnonymousClassBoundary::isDirectMember($method));
        $this->assertFalse(AnonymousClassBoundary::isDirectMember($const));
        $this->assertFalse(AnonymousClassBoundary::isInside($call));
    }

    public function testDetectsAnonymousClassNodeItself(): void
    {
        $anonymousNodes = $this->parseAnonymousClassSnippet();
        $namedNodes = $this->parseWithParents('<?php class Service {}');
        $finder = new NodeFinder();

        $anonymousClass = $finder->findFirst($anonymousNodes, static fn (Node $node): bool => $node instanceof Class_);
        $namedClass = $finder->findFirst($namedNodes, static fn (Node $node): bool => $node instanceof Class_);

        $this->assertInstanceOf(Class_::class, $anonymousClass);
        $this->assertInstanceOf(Class_::class, $namedClass);
        $this->assertTrue(AnonymousClassBoundary::isAnonymousClass($anonymousClass));
        $this->assertFalse(AnonymousClassBoundary::isAnonymousClass($namedClass));
    }

    /**
     * @return list<Node>
     */
    private function parseAnonymousClassSnippet(): array
    {
        return $this->parseWithParents(<<<'PHP'
<?php
$service = new class {
    public const TOKEN = 'x';

    public function run(): void
    {
        $this->call();
    }
};
PHP);
    }

    /**
     * @return list<Node>
     */
    private function parseWithParents(string $code): array
    {
        $nodes = $this->parsePhpFile($code);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new ParentConnectingVisitor());

        return $traverser->traverse($nodes);
    }
}
