<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Architecture;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

final class NoCatchAllExceptions extends Check
{
    /**
     * Report each matching PHP construct at its original source position.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        $nodes = (new NodeFinder)->find($file->nodes, fn (Node $node): bool => $this->isCatchAll($node));

        foreach ($nodes as $node) {
            yield new Violation(
                rule: $this->id(),
                file: $file->path,
                value: 1,
                threshold: 0,
                line: $node->getStartLine(),
                message: 'Catching Exception or Throwable hides unrelated failures. Catch the specific failure you can handle.',
            );
        }
    }

    /**
     * Determine whether catch all applies to the supplied input.
     */
    private function isCatchAll(Node $catch): bool
    {
        $isApplicable = $catch instanceof Stmt\Catch_;
        if (! $isApplicable) {
            return false;
        }

        foreach ($catch->types as $type) {
            $isBroadCatch = in_array($type->getLast(), ['Throwable', 'Exception'], true);
            if ($isBroadCatch) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return the stable identifier used in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'no_catch_all_exceptions';
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
