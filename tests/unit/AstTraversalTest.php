<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\NodeVisitorAbstract;
use PHPUnit\Framework\TestCase;

final class AstTraversalTest extends TestCase
{
    use ParsesPhpSnippets;

    public function testTraverseRunsVisitorOverNodes(): void
    {
        $visitor = new CountingVariableVisitor();

        (new NodeTraverser(new ParentConnectingVisitor(), $visitor))->traverse($this->parseSnippet('$a = $b;'));

        $this->assertSame(2, $visitor->variables);
    }

    public function testTraverseConnectsParentMetadata(): void
    {
        $visitor = new DynamicVariableParentVisitor();

        (new NodeTraverser(new ParentConnectingVisitor(), $visitor))->traverse($this->parseSnippet('$name = "value"; $$name = 1;'));

        $this->assertTrue($visitor->sawDynamicVariableNameWithParent);
    }

    public function testTraversePhpFileResolvesNamesAndConnectsParents(): void
    {
        $visitor = new ClassNameAndMethodParentVisitor();

        $this->traversePhpFile(
            <<<'PHP'
<?php
namespace App\Services;

class ReportService
{
    public function render(): void {}
}
PHP,
            $visitor,
        );

        $this->assertSame('App\Services\ReportService', $visitor->className);
        $this->assertTrue($visitor->methodHadClassParent);
    }

    public function testParentConnectionsDoNotResolveNames(): void
    {
        $visitor = new ClassNameAndMethodParentVisitor();

        (new NodeTraverser(new ParentConnectingVisitor(), $visitor))->traverse(
            $this->parseSnippet('namespace App\Services; class ReportService { public function render(): void {} }'),
        );

        $this->assertNull($visitor->className);
        $this->assertTrue($visitor->methodHadClassParent);
    }
}

final class CountingVariableVisitor extends NodeVisitorAbstract
{
    public int $variables = 0;

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Node\Expr\Variable) {
            $this->variables++;
        }

        return null;
    }
}

final class DynamicVariableParentVisitor extends NodeVisitorAbstract
{
    public bool $sawDynamicVariableNameWithParent = false;

    public function enterNode(Node $node): ?int
    {
        if (! $node instanceof Node\Expr\Variable || $node->name !== 'name') {
            return null;
        }

        $parent = $node->getAttribute('parent');

        if ($parent instanceof Node\Expr\Variable && $parent->name === $node) {
            $this->sawDynamicVariableNameWithParent = true;
        }

        return null;
    }
}

final class ClassNameAndMethodParentVisitor extends NodeVisitorAbstract
{
    public ?string $className = null;

    public bool $methodHadClassParent = false;

    public function enterNode(Node $node): ?int
    {
        if (
            $node instanceof Node\Stmt\Class_
            && isset($node->namespacedName)
            && $node->namespacedName instanceof Node\Name
        ) {
            $this->className = $node->namespacedName->toString();
        }

        if ($node instanceof Node\Stmt\ClassMethod && $node->getAttribute('parent') instanceof Node\Stmt\Class_) {
            $this->methodHadClassParent = true;
        }

        return null;
    }
}
