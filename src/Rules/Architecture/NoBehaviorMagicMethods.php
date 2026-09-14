<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Architecture;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

final class NoBehaviorMagicMethods extends Check
{
    /**
     * Report each matching PHP construct at its original source position.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        $nodes = (new NodeFinder)->find($file->nodes, fn (Node $node): bool => $this->isBehaviorMagicMethod($node));

        foreach ($nodes as $node) {
            yield new Violation(
                rule: $this->id(),
                file: $file->path,
                value: 1,
                threshold: 0,
                line: $node->getStartLine(),
                message: 'Behavioral magic methods hide the public API. Declare explicit methods and contracts.',
            );
        }
    }

    /**
     * Determine whether behavior magic method applies to the supplied input.
     */
    private function isBehaviorMagicMethod(Node $method): bool
    {
        $isApplicable = $method instanceof Stmt\ClassMethod;
        if (! $isApplicable) {
            return false;
        }

        return in_array(strtolower($method->name->toString()), ['__call', '__callstatic', '__get', '__set', '__isset', '__unset'], true);
    }

    /**
     * Return the stable identifier used in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'no_behavior_magic_methods';
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
