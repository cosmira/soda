<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis\Documentation;

use PhpParser\Comment\Doc;
use PhpParser\Node;

/**
 * Reads explicitly qualified types from declaration PHPDoc, not prose mentions.
 */
final readonly class PhpDocTypeReferences
{
    /**
     * Preserve explicit class types in parameter, result and property annotations.
     */
    public static function collect(Node $node): array
    {
        $comment = $node->getDocComment();
        if (! $comment instanceof Doc) {
            return [];
        }

        preg_match_all('/^\s*(?:\/\*\*|\*)\s*@(param|return|var|template(?:-covariant|-contravariant)?)\s+([^\r\n]+)/m', $comment->getText(), $annotations, PREG_SET_ORDER);
        $references = [];
        foreach ($annotations as $annotation) {
            array_shift($annotation);
            $tag = array_shift($annotation);
            $type = self::typeExpression($tag, array_shift($annotation));
            if (! self::isDeclarationTag($node, $tag)) {
                continue;
            }

            preg_match_all('/\\\\[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*/', $type, $classes);
            foreach (array_shift($classes) as $class) {
                $references[strtolower(ltrim($class, '\\'))] = true;
            }
        }

        return $references;
    }

    /**
     * Read the declared type or generic bound, excluding descriptions and default values.
     */
    private static function typeExpression(string $tag, string $text): string
    {
        $normalized = trim($text);
        $tokens = preg_split('/\s+/', $normalized);
        if ($tokens === false) {
            return '';
        }

        $first = array_shift($tokens) ?? '';
        $isTemplate = str_starts_with($tag, 'template');
        if (! $isTemplate) {
            return $first;
        }

        $relation = array_shift($tokens);
        if (! in_array($relation, ['of', 'as'], true)) {
            return '';
        }

        return array_shift($tokens) ?? '';
    }

    /**
     * Match annotation roles to declarations rather than accepting arbitrary comment text.
     */
    private static function isDeclarationTag(Node $node, string $tag): bool
    {
        return match ($tag) {
            'var'                                                      => $node instanceof Node\Stmt\Property,
            'param', 'return'                                          => $node instanceof Node\FunctionLike,
            'template', 'template-covariant', 'template-contravariant' => $node instanceof Node\Stmt\ClassLike || $node instanceof Node\FunctionLike,
            default                                                    => false,
        };
    }
}
