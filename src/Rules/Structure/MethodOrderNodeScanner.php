<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\FileFacts;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeFinder;

final readonly class MethodOrderNodeScanner
{
    /**
     * @return list<MethodOrderClassAnalysis>
     */
    public function scan(FileFacts $file): array
    {
        $nodes = $file->nodes;

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

            if ($analysis instanceof MethodOrderClassAnalysis) {
                $out[] = $analysis;
            }
        }

        return $out;
    }

    /**
     * Inspect the declared methods and local calls of a single class.
     */
    private function analyseClass(Class_|Trait_|Enum_ $class): ?MethodOrderClassAnalysis
    {
        $methods = array_values(array_filter(
            $class->getMethods(),
            static fn (ClassMethod $method): bool => $method->stmts !== null && ! $method->isAbstract(),
        ));
        $isSingleMethod = count($methods) < 2;

        if ($isSingleMethod) {
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
            $isVariable = $call->var instanceof Variable;
            if (! $isVariable) {
                continue;
            }

            if ($call->var->name !== 'this') {
                continue;
            }

            $isIdentifier = $call->name instanceof Identifier;
            if (! $isIdentifier) {
                continue;
            }

            $name = strtolower($call->name->toString());
            $isLocalCall = in_array($name, $declared, true) && ! in_array($name, $calls, true);

            if ($isLocalCall) {
                $calls[] = $name;
            }
        }

        return $calls;
    }

    /**
     * Return class name for the supplied analysis context.
     */
    private function className(ClassLike $class): string
    {
        if ($class->name instanceof Identifier) {
            return $class->name->toString();
        }

        return 'anonymous@'.$class->getLine();
    }
}
