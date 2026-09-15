<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Architecture;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\NodeFinder;

final class NoBooleanParameters extends Check
{
    use ReadonlyConstructorState;

    /**
     * Report each matching PHP construct at its original source position.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        $nodes = (new NodeFinder)->find($file->nodes, fn (Node $node): bool => $this->isBooleanParameter($node));

        foreach ($nodes as $node) {
            yield new Violation(
                rule: $this->id(),
                file: $file->path,
                value: 1,
                threshold: 0,
                line: $node->getStartLine(),
                message: 'Boolean parameter outside recognized readonly initialization. Check whether it selects behavior or carries data; use separate named methods for distinct behavior.',
            );
        }
    }

    /**
     * Determine whether boolean parameter applies to the supplied input.
     */
    private function isBooleanParameter(Node $parameter): bool
    {
        $isExcludedParameter = ! $parameter instanceof Node\Param;
        if ($isExcludedParameter) {
            return false;
        }

        $booleanDefault = $parameter->default instanceof Node\Expr\ConstFetch
            && in_array(strtolower($parameter->default->name->toString()), ['true', 'false'], true);
        $booleanType = $parameter->type instanceof Node && $this->hasBooleanType($parameter->type);

        return ($booleanDefault || $booleanType) && ! $this->isReadonlyState($parameter);
    }

    /**
     * Check the supplied input for boolean type.
     */
    private function hasBooleanType(Node $type): bool
    {
        if ($type instanceof Identifier) {
            return in_array(strtolower($type->name), ['bool', 'true', 'false'], true);
        }

        if ($type instanceof Node\NullableType) {
            return $this->hasBooleanType($type->type);
        }

        if ($type instanceof Node\UnionType) {
            foreach ($type->types as $member) {
                if ($this->hasBooleanType($member)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Return the stable identifier used in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'no_boolean_parameters';
    }

    /**
     * This check uses the resolved AST directly and needs no extra metrics.
     *
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return [];
    }
}
