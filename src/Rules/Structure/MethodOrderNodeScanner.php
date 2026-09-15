<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\FileFacts;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

final readonly class MethodOrderNodeScanner
{
    /**
     * Analyse each declaration independently of its surrounding lexical scope.
     *
     * @return list<MethodOrderClassAnalysis>
     */
    public function scan(FileFacts $file): array
    {
        $out = [];
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, Stmt\ClassLike::class) as $class) {
            $out[] = $this->analyseClass($class);
        }

        return $out;
    }

    /**
     * Restrict order constraints to private helpers with one unambiguous caller.
     */
    private function analyseClass(Stmt\ClassLike $class): MethodOrderClassAnalysis
    {
        $methods = $class->getMethods();
        $sequences = [];
        $private = [];
        foreach ($methods as $method) {
            $name = strtolower($method->name->toString());
            if ($method->isPrivate() && ! $method->isAbstract()) {
                $private[$name] = true;
            }

            $sequences[$name] = $this->sequence($method);
        }

        $owners = $this->owners($methods, $sequences);
        $analysis = [];
        foreach ($methods as $method) {
            $name = strtolower($method->name->toString());
            $calls = $this->exclusiveCalls($sequences[$name], $owners, $private, $name);
            $analysis[] = new MethodOrderMethod($method->name->toString(), $name, $method->getStartLine(), $calls);
        }

        return new MethodOrderClassAnalysis($class->name?->toString() ?? 'anonymous@'.$class->getStartLine(), $analysis);
    }

    /**
     * Count indirect uses too; unresolved calls cannot establish exclusive ownership.
     *
     * @param list<Stmt\ClassMethod>                               $methods
     * @param array<string, list<Expr\MethodCall|Expr\StaticCall>> $sequences
     *
     * @return array<string, list<string>>
     */
    private function owners(array $methods, array $sequences): array
    {
        $owners = [];
        foreach ($methods as $method) {
            $name = strtolower($method->name->toString());
            $callback = (new NodeFinder)->findFirst($method->stmts ?? [], static fn (Node $node): bool => $node instanceof Expr\Array_);
            if ($callback instanceof Node) {
                return [];
            }

            foreach ($this->allCalls($method) as $call) {
                if (! $call->name instanceof Node\Identifier) {
                    return [];
                }

                $target = $this->target($call);
                if ($target === null) {
                    return [];
                }

                $uses = $owners[$target] ?? [];
                $uses[] = $name;
                if (! in_array($call, $sequences[$name], true)) {
                    $uses[] = '<indirect>';
                }

                $owners[$target] = array_values(array_unique($uses));
            }
        }

        return $owners;
    }

    /**
     * Collect possible calls conservatively, including deferred bodies.
     *
     * @return list<Expr\MethodCall|Expr\StaticCall>
     */
    private function allCalls(Stmt\ClassMethod $method): array
    {
        $finder = new NodeFinder;

        return [...$finder->findInstanceOf($method->stmts ?? [], Expr\MethodCall::class),
            ...$finder->findInstanceOf($method->stmts ?? [], Expr\StaticCall::class)];
    }

    /**
     * Read only direct expression statements in a straight-line caller.
     *
     * @return list<Expr\MethodCall|Expr\StaticCall>
     */
    private function sequence(Stmt\ClassMethod $method): array
    {
        $calls = [];
        foreach ($method->stmts ?? [] as $statement) {
            if (! $statement instanceof Stmt\Expression) {
                return [];
            }

            $expression = $statement->expr;
            $direct = $expression instanceof Expr\MethodCall || $expression instanceof Expr\StaticCall;
            if ($direct && ! $expression->isFirstClassCallable()) {
                $calls[] = $expression;
            }
        }

        return $calls;
    }

    /**
     * Preserve only helpers whose ownership is established by the complete class.
     *
     * @param list<Expr\MethodCall|Expr\StaticCall> $sequence
     * @param array<string, list<string>>           $owners
     * @param array<string, true>                   $private
     *
     * @return list<string>
     */
    private function exclusiveCalls(array $sequence, array $owners, array $private, string $caller): array
    {
        $calls = [];
        foreach ($sequence as $call) {
            $target = $this->target($call);
            if ($target === null || $target === $caller) {
                continue;
            }

            if (isset($private[$target]) && count($owners[$target] ?? []) === 1) {
                $calls[] = $target;
            }
        }

        return $calls;
    }

    /**
     * Resolve case-insensitive lexical calls without guessing dynamic receivers.
     */
    private function target(Expr\MethodCall|Expr\StaticCall $call): ?string
    {
        if (! $call->name instanceof Node\Identifier) {
            return null;
        }

        $local = $call instanceof Expr\MethodCall
            ? $call->var instanceof Expr\Variable && $call->var->name === 'this'
            : $call->class instanceof Node\Name && strtolower($call->class->toString()) === 'self';

        return $local ? strtolower($call->name->toString()) : null;
    }
}
