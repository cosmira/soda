<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;

/**
 * Direct variable alias candidate: `$alias = $source;`.
 */
final readonly class UselessVariableAliasAssignment
{
    /**
     * Initialize the configured values and collaborators for this instance.
     */
    public function __construct(
        public string $variable,
        public string $source,
        public int $line,
    ) {}

    /**
     * Build the result from node.
     */
    public static function fromNode(Node $node): ?self
    {
        $isNonAlias = ! ($node instanceof Expression
            && $node->expr instanceof Assign
            && $node->expr->var instanceof Variable
            && $node->expr->expr instanceof Variable
            && is_string($node->expr->var->name)
            && is_string($node->expr->expr->name)
        );
        if ($isNonAlias) {
            return null;
        }

        return new self(
            $node->expr->var->name,
            $node->expr->expr->name,
            $node->getStartLine(),
        );
    }

    /**
     * @return array{line: int, variable: string, source: string}
     */
    public function toViolationRow(): array
    {
        return [
            'line'     => $this->line,
            'variable' => '$'.$this->variable,
            'source'   => '$'.$this->source,
        ];
    }
}
