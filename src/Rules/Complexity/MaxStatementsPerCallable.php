<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use InvalidArgumentException;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Limits executable statements per lexical callable independently of source formatting.
 */
#[Rule(
    label: 'Executable statements per callable:',
    advice: 'Name a cohesive step when it clarifies the operation; do not extract forwarding methods solely to lower the count.',
    default: 20,
)]
final class MaxStatementsPerCallable extends Check
{
    /**
     * Set the maximum statement count; zero accepts only empty callable bodies.
     */
    public function __construct(
        /**
         * Maximum number of executable statements accepted within one callable.
         */
        private readonly int $limit = 20,
    ) {
        throw_if($limit < 0, InvalidArgumentException::class, 'Statement limit must be non-negative.');
    }

    /**
     * Identify this metric in diagnostics and configuration.
     */
    public function id(): string
    {
        return 'max_statements_per_callable';
    }

    /**
     * Count syntax directly without requesting additional metric collectors.
     *
     * @return list<string>
     */
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Evaluate methods, named functions, closures and arrow functions separately.
     */
    public function checkFile(FileFacts $file): iterable
    {
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, FunctionLike::class) as $callable) {
            $statements = $callable->getStmts();
            if ($statements === null) {
                continue;
            }

            $count = $callable instanceof Node\Expr\ArrowFunction ? 1 : array_sum(array_map($this->statementCount(...), $statements));
            if ($count <= $this->limit) {
                continue;
            }

            $name = $this->callableName($callable);
            yield new Violation(
                rule: $this->id(), file: $file->path, value: $count, threshold: $this->limit,
                method: $name, class: str_contains($name, '::') ? strstr($name, '::', true) : null,
                line: $callable->getStartLine(),
                message: 'Callable contains too many executable statements. Name a cohesive step when it clarifies the operation; formatting does not reduce this count.',
            );
        }
    }

    /**
     * Nested declarations own their statements; control wrapper clauses add no instruction.
     */
    private function statementCount(Node $node): int
    {
        if ($node instanceof FunctionLike || $node instanceof Stmt\ClassLike) {
            return 0;
        }

        $wrappers = ['Stmt_Nop', 'Stmt_Else', 'Stmt_ElseIf', 'Stmt_Catch', 'Stmt_Finally', 'Stmt_Case'];
        $type = $node->getType();
        $count = $node instanceof Stmt && ! in_array($type, $wrappers, true) ? 1 : 0;
        foreach ($node->getSubNodeNames() as $name) {
            $children = get_object_vars($node)[$name];
            foreach (is_array($children) ? $children : [$children] as $child) {
                if ($child instanceof Node) {
                    $count += $this->statementCount($child);
                }
            }
        }

        return $count;
    }

    /**
     * Preserve resolved names and use source locations to distinguish anonymous callables.
     */
    private function callableName(FunctionLike $node): string
    {
        if ($node instanceof Stmt\Function_) {
            return $node->namespacedName?->toString() ?? $node->name->toString();
        }

        if ($node instanceof Stmt\ClassMethod) {
            $owner = $node->getAttribute('parent');
            $class = $owner instanceof Stmt\ClassLike ? $owner->namespacedName?->toString() : null;

            return ($class ?? '{anonymous-class}').'::'.$node->name->toString();
        }

        return '{closure@'.$node->getStartLine().'}';
    }
}
