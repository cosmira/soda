<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Documentation;

use PhpParser\Comment\Doc;
use PhpParser\Node;

final class MultilinePhpDocComment
{
    /**
     * Determine whether valid applies to the supplied input.
     */
    public function isValid(Node $node): bool
    {
        $comment = $node->getDocComment();
        $isDoc = $comment instanceof Doc;

        if (! $isDoc) {
            return false;
        }

        return $this->isMultilineWithContent($comment);
    }

    /**
     * Determine whether multiline with content applies to the supplied input.
     */
    private function isMultilineWithContent(Doc $comment): bool
    {
        $lines = preg_split('/\R/', trim($comment->getText()));
        $isTooShort = $lines === false || count($lines) < 3;

        if ($isTooShort) {
            return false;
        }

        $openingLine = array_shift($lines);
        $closingLine = array_pop($lines);

        return trim($openingLine) === '/**'
            && trim($closingLine) === '*/'
            && $this->hasContent($lines);
    }

    /**
     * @param list<string> $lines
     */
    private function hasContent(array $lines): bool
    {
        foreach ($lines as $line) {
            $hasContent = trim(ltrim(trim($line), '*')) !== '';
            if ($hasContent) {
                return true;
            }
        }

        return false;
    }
}
