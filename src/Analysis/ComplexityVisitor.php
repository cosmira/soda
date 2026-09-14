<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use function assert;
use function is_array;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use SebastianBergmann\Complexity\CyclomaticComplexityCalculatingVisitor;

/**
 * Collect cyclomatic scores for concrete methods and named functions, including enums.
 * Nested callable and class bodies are measured independently.
 */
final class ComplexityVisitor extends FactVisitor
{
    /**
     * @var array<string, positive-int>
     */
    private array $result = [];

    /**
     * Measure each concrete callable without changing traversal of nested scopes.
     */
    #[\Override]
    protected function doEnterNode(Node $node): void
    {
        $isNonCallable = ! $node instanceof ClassMethod && ! $node instanceof Function_;
        if ($isNonCallable) {
            return;
        }

        if ($node instanceof ClassMethod) {
            $isDeclaration = $node->getAttribute('parent') instanceof Interface_ || $node->isAbstract();
            if ($isDeclaration) {
                return;
            }

            $name = $this->methodName($node);
        }

        if ($node instanceof Function_) {
            $name = $this->functionName($node);
        }

        $statements = $node->getStmts();
        assert(is_array($statements));
        $this->result[$name] = $this->measure($statements);
    }

    /**
     * Return scores keyed by the same qualified names as the method facts.
     *
     * @return array<string, positive-int>
     */
    public function complexity(): array
    {
        return $this->result;
    }

    /**
     * @return non-empty-string
     */
    private function methodName(ClassMethod $node): string
    {
        $parent = $node->getAttribute('parent');

        assert($parent instanceof Class_ || $parent instanceof Trait_ || $parent instanceof Enum_);
        $isAnonymousClass = $parent->getAttribute('parent') instanceof New_;

        if ($isAnonymousClass) {
            return 'anonymous class';
        }

        assert(isset($parent->namespacedName));

        return $parent->namespacedName->toString().'::'.$node->name->toString();
    }

    /**
     * @return non-empty-string
     */
    private function functionName(Function_ $node): string
    {
        assert(isset($node->namespacedName));
        assert($node->namespacedName instanceof Name);

        $functionName = $node->namespacedName->toString();

        assert($functionName !== '');

        return $functionName;
    }

    /**
     * @param Stmt[] $statements
     *
     * @return positive-int
     */
    private function measure(array $statements): int
    {
        $visitor = new CyclomaticComplexityCalculatingVisitor();
        // Skip inner scopes: each has an independent complexity score.
        $boundary = new class extends NodeVisitorAbstract
        {
            /**
             * Visit the syntax node and return the appropriate traversal instruction.
             */
            public function enterNode(Node $node): ?int
            {
                $isScopeBoundary = $node instanceof Node\FunctionLike || $node instanceof Stmt\ClassLike;

                return $isScopeBoundary
                    ? NodeVisitor::DONT_TRAVERSE_CHILDREN
                    : null;
            }
        };
        (new NodeTraverser($boundary, $visitor))->traverse($statements);

        return $visitor->cyclomaticComplexity();
    }
}
