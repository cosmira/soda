<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use function ltrim;

use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;

use function strtolower;

/**
 * Records type references (params, returns, properties) into {@see EfferentCouplingGraph}.
 *
 * @internal
 */
final readonly class EfferentCouplingReferences
{
    /**
     * Initialize the configured values and collaborators for this instance.
     */
    public function __construct(
        private EfferentCouplingGraph $graph,
    ) {}

    /**
     * Ingest parsed type hint using the current analysis state.
     */
    public function recordType(Node|ComplexType|null $type): void
    {
        $isNode = $type instanceof Node;
        if (! $isNode) {
            return;
        }

        if ($type instanceof NullableType) {
            $this->recordType($type->type);

            return;
        }

        $isCompositeType = $type instanceof UnionType || $type instanceof IntersectionType;

        if ($isCompositeType) {
            foreach ($type->types as $inner) {
                $this->recordType($inner);
            }

            return;
        }

        if ($type instanceof Identifier) {
            $this->recordIdentifier($type);

            return;
        }

        if ($type instanceof Name) {
            $this->recordName($type);
        }
    }

    /**
     * Record non builtin named identifier in the current analysis scope.
     */
    private function recordIdentifier(Identifier $type): void
    {
        $isBuiltin = $this->isPhpBuiltinIdentifier($type->toLowerString());
        if (! $isBuiltin) {
            $this->graph->addEdge($type->toString());
        }
    }

    /**
     * Register referenced class name using the current analysis state.
     */
    public function recordName(?Name $name): void
    {
        $isName = $name instanceof Name;
        if (! $isName) {
            return;
        }

        $lower = strtolower($name->toString());
        $isOwnerAlias = $lower === 'self' || $lower === 'static';

        if ($isOwnerAlias) {
            return;
        }

        if ($lower === 'parent') {
            $parentClass = $this->graph->currentParent();

            if ($parentClass !== null) {
                $this->graph->addEdge($parentClass);
            }

            return;
        }

        $fqcn = $this->className($name);

        if ($fqcn !== null) {
            $this->graph->addEdge($fqcn);
        }
    }

    /**
     * Register class operand from expression using the current analysis state.
     */
    public function recordClassOperand(Node $expr): void
    {
        if ($expr instanceof Name) {
            $this->recordName($expr);
        }
    }

    /**
     * @psalm-return non-empty-string|null
     */
    public function className(Name $name): ?string
    {
        $s = ltrim($name->toString(), '\\');

        return $s !== '' ? $s : null;
    }

    /**
     * Determine whether php builtin identifier applies to the supplied input.
     */
    private function isPhpBuiltinIdentifier(string $lower): bool
    {
        return isset([
            'int'      => true,
            'string'   => true,
            'float'    => true,
            'bool'     => true,
            'array'    => true,
            'callable' => true,
            'iterable' => true,
            'object'   => true,
            'mixed'    => true,
            'never'    => true,
            'void'     => true,
            'false'    => true,
            'true'     => true,
            'null'     => true,
            'static'   => true,
        ][$lower]);
    }
}
