<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\Rule;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

final class MethodOrderNodeScanner
{
    /**
     * @return list<MethodOrderClassAnalysis>
     */
    public function scan(string $file): array
    {
        $nodes = $this->parse($file);

        if ($nodes === []) {
            return [];
        }

        $classes = (new NodeFinder)->find(
            $nodes,
            static fn (Node $node): bool => $node instanceof Class_ || $node instanceof Trait_ || $node instanceof Enum_,
        );
        $out = [];

        foreach ($classes as $class) {
            $analysis = $this->analyseClass($class);

            if ($analysis !== null) {
                $out[] = $analysis;
            }
        }

        return $out;
    }

    /**
     * @return list<Node>
     */
    private function parse(string $file): array
    {
        $source = @file_get_contents($file);

        if ($source === false || $source === '') {
            return [];
        }

        try {
            return (new ParserFactory)->createForNewestSupportedVersion()->parse($source) ?? [];
        } catch (Error) {
            return [];
        }
    }

    private function analyseClass(Class_|Trait_|Enum_ $class): ?MethodOrderClassAnalysis
    {
        $methods = array_values(array_filter(
            $class->getMethods(),
            static fn ($method): bool => $method->stmts !== null && ! $method->isAbstract(),
        ));

        if (count($methods) < 2) {
            return null;
        }

        $declared = [];
        foreach ($methods as $method) {
            $declared[] = strtolower($method->name->toString());
        }

        $out = [];
        foreach ($methods as $method) {
            $out[] = new MethodOrderMethod(
                $method->name->toString(),
                strtolower($method->name->toString()),
                $method->getLine(),
                $this->calls($method->stmts ?? [], $declared),
            );
        }

        return new MethodOrderClassAnalysis($this->className($class), $out);
    }

    /**
     * @param list<Node>   $nodes
     * @param list<string> $declared
     *
     * @return list<string>
     */
    private function calls(array $nodes, array $declared): array
    {
        $calls = [];

        foreach ((new NodeFinder)->findInstanceOf($nodes, MethodCall::class) as $call) {
            if (! $call->var instanceof Variable || $call->var->name !== 'this' || ! $call->name instanceof Identifier) {
                continue;
            }

            $name = strtolower($call->name->toString());

            if (in_array($name, $declared, true) && ! in_array($name, $calls, true)) {
                $calls[] = $name;
            }
        }

        return $calls;
    }

    private function className(ClassLike $class): string
    {
        if ($class->name !== null) {
            return $class->name->toString();
        }

        return 'anonymous@'.$class->getLine();
    }
}
