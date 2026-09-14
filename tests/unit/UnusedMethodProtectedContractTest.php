<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Rules\Usage\UnusedMethodProtectedContract;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeFinder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UnusedMethodProtectedContractTest extends TestCase
{
    use ParsesPhpSnippets;

    public function testOverrideAttributeDeclaresExternalContract(): void
    {
        [$type, $method, $byName] = $this->methodContext('class Child { #[\Override] protected function helper(): void {} }');

        $this->assertTrue(UnusedMethodProtectedContract::isDeclaredOnSupertype($method, $type, $byName));
    }

    public function testAbstractAncestorMethodDeclaresContract(): void
    {
        [$type, $method, $byName] = $this->methodContext(
            'abstract class ParentType { protected function helper(): void {} } class Child extends ParentType { protected function helper(): void {} }',
        );

        $this->assertTrue(UnusedMethodProtectedContract::isDeclaredOnSupertype($method, $type, $byName));
    }

    public function testImplementedInterfaceMethodDeclaresContract(): void
    {
        [$type, $method, $byName] = $this->methodContext(
            'interface Contract { protected function helper(): void; } class Child implements Contract { protected function helper(): void {} }',
        );

        $this->assertTrue(UnusedMethodProtectedContract::isDeclaredOnSupertype($method, $type, $byName));
    }

    public function testConcreteAncestorAndUnrelatedInterfaceDoNotDeclareContract(): void
    {
        [$type, $method, $byName] = $this->methodContext(
            'class ParentType { protected function helper(): void {} } interface Contract { protected function other(): void; } class Child extends ParentType implements Contract { protected function helper(): void {} }',
        );

        $this->assertFalse(UnusedMethodProtectedContract::isDeclaredOnSupertype($method, $type, $byName));
    }

    #[DataProvider('hierarchies')]
    public function testResolvesInFileHierarchyWithoutLooping(string $code, bool $expected): void
    {
        [$type, $method, $byName] = $this->methodContext($code);

        $this->assertSame($expected, UnusedMethodProtectedContract::isDeclaredOnSupertype($method, $type, $byName));
    }

    public static function hierarchies(): iterable
    {
        yield 'grandparent' => ['abstract class GrandparentType { protected function helper() {} } class ParentType extends GrandparentType {} class Child extends ParentType { protected function helper() {} }', true];
        yield 'missing parent' => ['class Child extends MissingParent { protected function helper() {} }', false];
        yield 'interface as parent' => ['interface Contract { public function helper(); } class Child extends Contract { protected function helper() {} }', false];
        yield 'unrelated interface method' => ['interface Contract { public function other(); } class Child implements Contract { protected function helper() {} }', false];
        yield 'case sensitivity preserved' => ['abstract class ParentType { protected function Helper() {} } class Child extends ParentType { protected function helper() {} }', false];
        yield 'cyclic classes' => ['class ParentType extends Child {} class Child extends ParentType { protected function helper() {} }', false];
        yield 'self inheritance' => ['class Child extends Child { protected function helper() {} }', false];
    }

    /**
     * @return array{0: Class_, 1: ClassMethod, 2: array<string, Class_|Interface_|Trait_>}
     */
    private function methodContext(string $code, string $typeName = 'Child', string $methodName = 'helper'): array
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
            if (! $type instanceof Class_ && ! $type instanceof Interface_ && ! $type instanceof Trait_) {
                continue;
            }

            $this->assertNotNull($type->name);
            $types[$type->name->toString()] = $type;
        }

        return $types;
    }
}
