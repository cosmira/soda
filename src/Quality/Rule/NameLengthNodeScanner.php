<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\Rule;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

final class NameLengthNodeScanner
{
    /**
     * @param list<string> $rules
     *
     * @return list<NameLengthOccurrence>
     */
    public function scan(string $file, array $rules): array
    {
        $nodes = $this->parse($file);

        if ($nodes === []) {
            return [];
        }

        $visitor = new NameLengthCollectingVisitor($rules);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($nodes);

        return $visitor->occurrences();
    }

    /**
     * @return list<Node>
     */
    private function parse(string $file): array
    {
        $source = @file_get_contents($file);

        if ($source === false || $source === '') {
            return [];
        }

        try {
            return (new ParserFactory)->createForNewestSupportedVersion()->parse($source) ?? [];
        } catch (Error) {
            return [];
        }
    }
}
