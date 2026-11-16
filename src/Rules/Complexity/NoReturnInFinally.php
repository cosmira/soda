<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Rejects returns executed in finally blocks because they can replace outcomes or suppress exceptions.
 */
#[Rule(
    label: 'Returns inside finally:',
    advice: 'Keep cleanup in finally and return from the normal control flow without replacing pending outcomes.',
    severity: 'error',
)]
final class NoReturnInFinally extends Check
{
    /**
     * Identify the finally return policy.
     */
    public function id(): string
    {
        return 'no_return_in_finally';
    }

    /**
     * Inspect the resolved AST and its lexical parent connections.
     *
     * @return list<string>
     */
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Report explicit returns belonging to a finally in the same lexical callable.
     */
    public function checkFile(FileFacts $file): iterable
    {
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, Stmt\Return_::class) as $node) {
            if (! $this->isInsideFinally($node)) {
                continue;
            }

            yield new Violation(
                rule: $this->id(), file: $file->path, value: 1, threshold: 0,
                line: $node->getStartLine(),
                message: 'Return inside finally can replace a pending return or suppress an exception. Keep cleanup in finally and return from the normal control flow.',
            );
        }
    }

    /**
     * Stop at callable and type boundaries so deferred returns are not attributed to cleanup.
     */
    private function isInsideFinally(Node $node): bool
    {
        $parent = $node->getAttribute('parent');
        while ($parent instanceof Node) {
            if ($parent instanceof Stmt\Finally_) {
                return true;
            }

            if ($parent instanceof Node\FunctionLike || $parent instanceof Stmt\ClassLike) {
                return false;
            }

            $parent = $parent->getAttribute('parent');
        }

        return false;
    }
}
