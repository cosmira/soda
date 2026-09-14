<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;

trait ParsesPhpSnippets
{
    /**
     * @return list<Node>
     */
    private function parseSnippet(string $code): array
    {
        $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse('<?php '.$code);

        $this->assertIsArray($nodes);

        return $nodes;
    }

    /**
     * @return list<Node>
     */
    private function parsePhpFile(string $code): array
    {
        $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse($code);

        $this->assertIsArray($nodes);

        return $nodes;
    }

    private function traversePhpFile(string $code, NodeVisitor $visitor): void
    {
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor(new ParentConnectingVisitor());
        $traverser->addVisitor($visitor);
        $traverser->traverse($this->parsePhpFile($code));
    }

    private function firstStatement(string $code): Node
    {
        $nodes = $this->parseSnippet($code);

        $this->assertArrayHasKey(0, $nodes);

        return $nodes[0];
    }

    private function firstExpression(string $code): Node
    {
        $statement = $this->firstStatement($code);

        if ($statement instanceof Expression || $statement instanceof Return_) {
            $this->assertInstanceOf(Node::class, $statement->expr);

            return $statement->expr;
        }

        $this->fail('Expected an expression-bearing statement.');
    }

    private function firstAssignedExpression(string $code): Node
    {
        $expression = $this->firstExpression($code);

        $this->assertInstanceOf(Assign::class, $expression);

        return $expression->expr;
    }

    private function firstFunctionCallArgument(string $code): Arg
    {
        $expression = $this->firstExpression($code);

        $this->assertInstanceOf(FuncCall::class, $expression);
        $this->assertArrayHasKey(0, $expression->args);

        return $expression->args[0];
    }
}
