<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Architecture;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Collects local instance-method relationships for the owning cohesion graph.
 */
trait CohesionMethods
{
    /**
     * Constructors, abstract declarations and static methods are outside this variant.
     *
     * @return array<string, array>
     */
    private function methods(Stmt\ClassLike $type): array
    {
        $methods = [];
        foreach ($type->getMethods() as $method) {
            $isExcluded = $method->isStatic() || $method->isAbstract() || $method->name->toLowerString() === '__construct';
            if ($isExcluded) {
                continue;
            }

            $methods[$method->name->toLowerString()] = [
                'name'       => $method->name->toString(), 'line' => $method->getStartLine(),
                'visibility' => $this->visibility($method->flags),
                ...$this->relations($method->stmts ?? []),
            ];
        }

        return $methods;
    }

    /**
     * Keep literal fields and calls; mark dynamic/local-alias uncertainty explicitly.
     *
     * @param list<Node> $nodes
     *
     * @return array{fields: list<string>, calls: list<string>, unknown: list<string>}
     */
    private function relations(array $nodes): array
    {
        $result = ['fields' => [], 'calls' => [], 'unknown' => []];
        while ($nodes !== []) {
            $node = array_pop($nodes);
            if ($node instanceof Node\FunctionLike) {
                $result['unknown'] = [...$result['unknown'], 'nested callable may capture this'];

                continue;
            }

            if ($node instanceof Stmt\ClassLike) {
                continue;
            }

            $result = $this->relation($node, $result);
            array_push($nodes, ...$this->children($node));
        }

        $result['calls'] = array_map(strtolower(...), $result['calls']);

        return array_map(fn (array $items): array => array_values(array_unique($items)), $result);
    }

    /**
     * Keep each kind of evidence independent while sharing one method fact row.
     */
    private function relation(Node $node, array $result): array
    {
        $result = $this->localRelation($node, $result);
        $result = $this->staticRelation($node, $result);

        return $this->thisUsage($node, $result);
    }

    /**
     * Record literal instance accesses and preserve uncertainty about dynamic names.
     */
    private function localRelation(Node $node, array $result): array
    {
        $isAccess = $node instanceof Expr\PropertyFetch || $node instanceof Expr\NullsafePropertyFetch
            || $node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall;
        $isLocal = $isAccess && $node->var instanceof Expr\Variable && $node->var->name === 'this';
        if (! $isLocal) {
            return $result;
        }

        $isField = $node instanceof Expr\PropertyFetch || $node instanceof Expr\NullsafePropertyFetch;
        $key = $isField ? 'fields' : 'calls';
        if ($node->name instanceof Node\Identifier) {
            $result[$key] = [...$result[$key], $node->name->toString()];

            return $result;
        }

        $result['unknown'] = [...$result['unknown'], 'dynamic this member'];

        return $result;
    }

    /**
     * Literal self calls can resolve locally; parent and late static binding need more scope.
     */
    private function staticRelation(Node $node, array $result): array
    {
        $isStatic = $node instanceof Expr\StaticCall && $node->class instanceof Node\Name;
        if (! $isStatic) {
            return $result;
        }

        $receiver = strtolower($node->class->toString());
        $isSelf = $receiver === 'self' && $node->name instanceof Node\Identifier;
        if ($isSelf) {
            $result['calls'] = [...$result['calls'], $node->name->toLowerString()];

            return $result;
        }

        $isLateBound = in_array($receiver, ['self', 'parent', 'static'], true);
        if ($isLateBound) {
            $result['unknown'] = [...$result['unknown'], 'unresolved '.$receiver.' call'];
        }

        return $result;
    }

    /**
     * Classify uses of this by the parser's node discriminator, including implicit protocols.
     */
    private function thisUsage(Node $node, array $result): array
    {
        $parent = $node->getAttribute('parent');
        $isThis = $node instanceof Expr\Variable && $node->name === 'this';
        $isLocal = $isThis && $parent instanceof Node;
        if (! $isLocal) {
            return $result;
        }

        $evidence = match ($parent->getType()) {
            'Expr_Cast_String'           => ['calls', '__tostring'],
            'Stmt_Foreach', 'Expr_Clone' => ['unknown', 'implicit iteration or clone of this'],
            'Arg', 'Expr_Assign'         => ['unknown', 'this passed or aliased'],
            default                      => null,
        };
        if ($evidence !== null) {
            [$category, $reason] = $evidence;
            $result[$category] = [...$result[$category], $reason];
        }

        return $result;
    }

    /**
     * Traverse only syntax children, never parent attributes or attached analysis metadata.
     *
     * @return iterable<Node>
     */
    private function children(Node $node): iterable
    {
        $properties = get_object_vars($node);
        foreach ($node->getSubNodeNames() as $field) {
            $value = $properties[$field];
            foreach (is_array($value) ? $value : [$value] as $child) {
                if ($child instanceof Node) {
                    yield $child;
                }
            }
        }
    }
}
