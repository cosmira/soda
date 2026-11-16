<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Architecture;

use Cosmira\Soda\Analysis\CallableIdentity;
use Cosmira\Soda\Analysis\Complexity\StatementLists;
use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node;
use PhpParser\Node\Stmt;

#[Rule(
    label: 'Catch-all exceptions:',
    advice: 'Return an explicit fallback value, propagate or translate the failure; avoid silently continuing after a broad catch.',
)]
final class NoCatchAllExceptions extends Check
{
    /**
     * Preserve declared cleanup contracts that deliberately retain an earlier failure.
     *
     * @param list<string> $contracts Exact qualified Class::method identities; masks and inherited overrides do not match.
     */
    public function __construct(private readonly array $contracts = []) {}

    /**
     * Report each matching PHP construct at its original source position.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        $contracts = array_fill_keys(array_map(strtolower(...), $this->contracts), true);
        $nodes = $this->catches($file->nodes);
        $outcomes = $file->metrics['terminalOutcomes'] ?? [];
        $deferred = $outcomes['catches'] ?? [];
        $returned = $outcomes['returnedCatches'] ?? [];

        foreach ($nodes as $node) {
            $hasReturnedFallback = isset($returned[$node->getStartFilePos()]) && ! $this->hasEnclosingFinally($node);
            if (! $this->isCatchAll($node) || $this->hasExplicitOutcome($node->stmts) || $hasReturnedFallback || isset($deferred[$node->getStartFilePos()])) {
                continue;
            }

            $identity = CallableIdentity::enclosingMethod($node);
            $key = $identity !== null ? strtolower($identity) : null;
            if ($key !== null && isset($contracts[$key])) {
                continue;
            }

            yield $this->violation($file->path, $node->getStartLine());
        }
    }

    /**
     * An enclosing finally may replace the sibling return, but an outer callable cannot do so.
     */
    private function hasEnclosingFinally(Node $node): bool
    {
        $parent = $node->getAttribute('parent');
        while ($parent instanceof Node && ! $parent instanceof Node\FunctionLike) {
            if ($parent instanceof Stmt\TryCatch && $parent->finally instanceof Node) {
                return true;
            }

            $parent = $parent->getAttribute('parent');
        }

        return false;
    }

    /**
     * Exact static targets are checked only after all selected source declarations are known.
     */
    public function checkProject(ProjectFacts $project): iterable
    {
        $declarations = $this->projectDeclarations($project);
        $contracts = array_fill_keys(array_map(strtolower(...), $this->contracts), true);
        foreach ($project->files as $file => $facts) {
            yield from $this->deferredCatches($file, $facts['terminalOutcomes'] ?? [], $declarations, $contracts);
        }
    }

    /**
     * Duplicate source identities cannot establish a terminal target.
     */
    private function projectDeclarations(ProjectFacts $project): array
    {
        $types = [];
        $callables = [];
        foreach ($project->files as $facts) {
            $outcomes = $facts['terminalOutcomes'] ?? [];
            foreach ($outcomes['types'] ?? [] as $name => $type) {
                $types[$name] = isset($types[$name]) ? ['declared' => false, 'standalone' => false] : $type;
            }

            foreach ($outcomes['callables'] ?? [] as $name => $callable) {
                $callables[$name] = isset($callables[$name]) ? ['terminal' => false] : $callable;
            }
        }

        return ['types' => $types, 'callables' => $callables];
    }

    /**
     * Deferred catches retain their source order and the same exact contract exclusions.
     */
    private function deferredCatches(string $file, array $outcomes, array $declarations, array $contracts): iterable
    {
        ['types' => $types, 'callables' => $callables] = $declarations;
        foreach ($outcomes['catches'] ?? [] as $catch) {
            $identity = strtolower($catch['identity'] ?? '');
            if (array_intersect($catch['types'], ['Throwable', 'Exception']) === [] || isset($contracts[$identity]) || $this->hasTerminalTarget($catch, $types, $callables)) {
                continue;
            }

            yield $this->violation($file, $catch['line']);
        }
    }

    /**
     * Only an own public static body can discharge an explicitly named source call.
     */
    private function hasTerminalTarget(array $catch, array $types, array $callables): bool
    {
        $type = $types[$catch['class']] ?? [];
        $callable = $callables[$catch['class'].'::'.$catch['method']] ?? [];
        if (! ($type['declared'] ?? false) || ! ($type['standalone'] ?? false) || ! ($callable['terminal'] ?? false)) {
            return false;
        }

        return $callable['static'] && $callable['visibility'] === 'public'
            && $catch['arity'] >= $callable['required'] && $catch['arity'] <= $callable['maximum'];
    }

    /**
     * Nested callable catches retain their own original syntax locations.
     *
     * @return list<Stmt\Catch_>
     */
    private function catches(array $nodes): array
    {
        $catches = [];
        foreach ($nodes as $node) {
            if (is_array($node)) {
                array_push($catches, ...$this->catches($node));
            } elseif ($node instanceof Node) {
                if ($node instanceof Stmt\Catch_) {
                    $catches[] = $node;
                }

                array_push($catches, ...$this->catches(get_object_vars($node)));
            }
        }

        return $catches;
    }

    /**
     * Preserve the diagnostic fields for both immediate and project-deferred catches.
     */
    private function violation(string $file, int $line): Violation
    {
        return new Violation(rule: $this->id(), file: $file, value: 1, threshold: 0, line: $line,
            message: 'A broad catch continues without returning a value or throwing. Make the failure outcome explicit.');
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
     * Recognize catch bodies that explicitly return, throw, or otherwise handle failure.
     *
     * @param list<Stmt> $statements Statements in the current catch scope.
     */
    private function hasExplicitOutcome(array $statements): bool
    {
        foreach ($statements as $statement) {
            if ($this->hasBareReturn($statement)) {
                return false;
            }

            $isTermination = $statement instanceof Stmt\Expression && StatementLists::isExit($statement);
            if ($statement instanceof Stmt\Return_ || $isTermination) {
                return true;
            }

            if ($statement instanceof Stmt\If_ && $this->hasOutcomeInEveryBranch($statement)) {
                return true;
            }

            if ($statement instanceof Stmt\TryCatch && $this->hasOutcomeInEveryCatch($statement)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ignore nested function scopes when checking void outcomes.
     */
    private function hasBareReturn(Node $node): bool
    {
        if ($node instanceof Node\FunctionLike) {
            return false;
        }

        if ($node instanceof Stmt\Return_ && ! $node->expr instanceof Node) {
            return true;
        }

        foreach (get_object_vars($node) as $value) {
            $children = is_array($value) ? $value : [$value];
            foreach ($children as $child) {
                if ($child instanceof Node && $this->hasBareReturn($child)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Require an explicit outcome in every branch, including else.
     */
    private function hasOutcomeInEveryBranch(Stmt\If_ $statement): bool
    {
        if (! $statement->else instanceof Node || ! $this->hasExplicitOutcome($statement->stmts)) {
            return false;
        }

        foreach ($statement->elseifs as $branch) {
            if (! $this->hasExplicitOutcome($branch->stmts)) {
                return false;
            }
        }

        return $this->hasExplicitOutcome($statement->else->stmts);
    }

    /**
     * Every normal and recovery path must finish before a following statement.
     */
    private function hasOutcomeInEveryCatch(Stmt\TryCatch $statement): bool
    {
        if ($statement->finally instanceof Node || $this->hasJump($statement) || ! $this->hasExplicitOutcome($statement->stmts)) {
            return false;
        }

        foreach ($statement->catches as $catch) {
            if (! $this->hasExplicitOutcome($catch->stmts)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A jump can bypass a later return or leave the nested recovery block.
     */
    private function hasJump(Node $node): bool
    {
        if ($node instanceof Node\FunctionLike) {
            return false;
        }

        if (in_array($node->getType(), ['Stmt_Break', 'Stmt_Continue', 'Stmt_Goto'], true)) {
            return true;
        }

        foreach (get_object_vars($node) as $value) {
            foreach (is_array($value) ? $value : [$value] as $child) {
                if ($child instanceof Node && $this->hasJump($child)) {
                    return true;
                }
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
     * Link scalar static-call bindings to source terminal declarations after file analysis.
     *
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return ['terminalOutcomes'];
    }
}
