<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use PhpParser\Node;

/**
 * @internal
 */
final class ControlStructureMatcher
{
    /**
     * Defines control structure node kinds used by this policy.
     */
    private const array CONTROL_STRUCTURE_NODE_KINDS = [
        'Stmt_If'       => true,
        'Stmt_For'      => true,
        'Stmt_Foreach'  => true,
        'Stmt_While'    => true,
        'Stmt_Do'       => true,
        'Stmt_Switch'   => true,
        'Stmt_TryCatch' => true,
    ];

    /**
     * Determine whether control structure applies to the supplied input.
     */
    public static function isControlStructure(Node $syntaxNode): bool
    {
        return isset(self::CONTROL_STRUCTURE_NODE_KINDS[$syntaxNode->getType()]);
    }
}
