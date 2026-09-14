<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Rules\Usage\UnusedMethodCandidatePolicy;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeFinder;
use PHPUnit\Framework\TestCase;

final class UnusedMethodCandidatePolicyTest extends TestCase
{
    use ParsesPhpSnippets;

    public function testPrivateMethodIsCandidate(): void
    {
        [$type, $method, $byName] = $this->methodContext('class A { private function helper(): void {} }');

        $this->assertTrue(UnusedMethodCandidatePolicy::isCandidate($method, $type, $byName));
    }

    public function testPublicAbstractAndMagicMethodsAreNotCandidates(): void
    {
        [$publicType, $publicMethod, $publicByName] = $this->methodContext('class A { public function helper(): void {} }');
        [$abstractType, $abstractMethod, $abstractByName] = $this->methodContext('abstract class A { abstract protected function helper(): void; }');
        [$magicType, $magicMethod, $magicByName] = $this->methodContext('class A { private function __toString(): string { return ""; } }', methodName: '__toString');

        $this->assertFalse(UnusedMethodCandidatePolicy::isCandidate($publicMethod, $publicType, $publicByName));
        $this->assertFalse(UnusedMethodCandidatePolicy::isCandidate($abstractMethod, $abstractType, $abstractByName));
        $this->assertFalse(UnusedMethodCandidatePolicy::isCandidate($magicMethod, $magicType, $magicByName));
    }

    public function testProtectedMethodWithoutContractIsCandidate(): void
    {
        [$type, $method, $byName] = $this->methodContext('class A { protected function helper(): void {} }');

        $this->assertTrue(UnusedMethodCandidatePolicy::isCandidate($method, $type, $byName));
    }

    public function testFinalProtectedOnAbstractClassIsNotCandidate(): void
    {
        [$type, $method, $byName] = $this->methodContext('abstract class A { final protected function helper(): void {} }');

        $this->assertFalse(UnusedMethodCandidatePolicy::isCandidate($method, $type, $byName));
    }

    public function testProtectedOverrideOfAbstractAncestorIsNotCandidate(): void
    {
        [$type, $method, $byName] = $this->methodContext(
            'abstract class A { abstract protected function helper(): void; } class B extends A { protected function helper(): void {} }',
            'B',
            'helper',
        );

        $this->assertFalse(UnusedMethodCandidatePolicy::isCandidate($method, $type, $byName));
    }

    /**
     * @return array{0: Class_|Trait_, 1: ClassMethod, 2: array<string, Class_|Interface_|Trait_>}
     */
    private function methodContext(string $code, string $typeName = 'A', string $methodName = 'helper'): array
    {
        $nodes = $this->parseSnippet($code);
        $finder = new NodeFinder();
        $types = $this->typesByName($nodes, $finder);
        $type = $types[$typeName];

        $this->assertInstanceOf(Class_::class, $type);

        $method = $finder->findFirst(
            [$type],
            static fn (Node $node): bool => $node instanceof ClassMethod && $node->name->toString() === $methodName,
        );

        $this->assertInstanceOf(ClassMethod::class, $method);

        return [$type, $method, $types];
    }

    /**
     * @param list<Node> $nodes
     *
     * @return array<string, Class_|Interface_|Trait_>
     */
    private function typesByName(array $nodes, NodeFinder $finder): array
    {
        $types = [];

        foreach ($finder->find($nodes, static fn (Node $node): bool => $node instanceof Class_ || $node instanceof Interface_ || $node instanceof Trait_) as $type) {
            $this->assertInstanceOf(Class_::class, $type);
            $this->assertNotNull($type->name);
            $types[$type->name->toString()] = $type;
        }

        return $types;
    }
}
