<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Architecture;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

#[Rule(
    label: 'Object cloning:',
    advice: 'This policy forbids every clone, including explicit copying APIs. Fresh construction satisfies it; clone-based value APIs require choosing a different rule selection.',
)]
final class NoObjectCloning extends Check
{
    /**
     * Report each matching PHP construct at its original source position.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        $nodes = (new NodeFinder)->find($file->nodes, fn (Node $node): bool => $node instanceof Expr\Clone_);

        foreach ($nodes as $node) {
            yield new Violation(
                rule: $this->id(),
                file: $file->path,
                value: 1,
                threshold: 0,
                line: $node->getStartLine(),
                message: 'This rule forbids every clone, including explicit copy methods. Construct a new value to satisfy this policy; the syntax alone does not prove unsafe copying.',
            );
        }
    }

    /**
     * Return the stable identifier used in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'no_object_cloning';
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
