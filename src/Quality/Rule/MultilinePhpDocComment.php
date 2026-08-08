<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\Rule;

use PhpParser\Comment\Doc;
use PhpParser\Node;

final class MultilinePhpDocComment
{
    public function isValid(Node $node): bool
    {
        $comment = $node->getDocComment();

        if (! $comment instanceof Doc) {
            return false;
        }

        return $this->isMultilineWithContent($comment);
    }

    private function isMultilineWithContent(Doc $comment): bool
    {
        $lines = preg_split('/\R/', trim($comment->getText()));

        if ($lines === false || count($lines) < 3) {
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
            if (trim(ltrim(trim($line), '*')) !== '') {
                return true;
            }
        }

        return false;
    }
}
