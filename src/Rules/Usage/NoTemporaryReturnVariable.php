<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Finally_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\NodeFinder;
use PhpToken;

/** Forbids a local variable created only to return its value immediately. */
#[Rule(
    label: 'Temporary return variables:',
    advice: 'Return the expression directly instead of assigning a temporary variable.',
)]
final class NoTemporaryReturnVariable extends Check
{
    /**
     * Return the stable rule identifier.
     */
    #[\Override]
    public function id(): string
    {
        return 'no_temporary_return_variable';
    }

    /**
     * Report redundant return assignments in callable bodies.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $facts): iterable
    {
        $finder = new NodeFinder;
        foreach ($finder->find($facts->nodes, static fn (Node $node): bool => $node instanceof FunctionLike) as $scope) {
            assert($scope instanceof FunctionLike);
            if ($scope->returnsByRef()) {
                continue;
            }

            yield from $this->checkScope($scope, $facts, $finder);
        }
    }

    /**
     * Check consecutive statements within one callable.
     *
     * @return iterable<Violation>
     */
    private function checkScope(FunctionLike $scope, FileFacts $facts, NodeFinder $finder): iterable
    {
        foreach ($this->statementLists($scope) as $statements) {
            foreach ($statements as $index => $statement) {
                $variable = $this->returnedVariable($statement, $statements[$index + 1] ?? null);
                if ($variable === null || $this->hasTypeAnnotation($statement, $variable)) {
                    continue;
                }

                $uses = $finder->find([$scope], static fn (Node $node): bool => $node instanceof Variable
                    && (! is_string($node->name) || $node->name === $variable));
                if (count($uses) === 2) {
                    yield new Violation(rule: $this->id(), file: $facts->path, value: 1, threshold: 0, line: $statement->getStartLine(), message: 'Return the expression directly instead of assigning a temporary variable.');
                }
            }
        }
    }

    /**
     * Identify a simple assignment followed by returning its target.
     */
    private function returnedVariable(Node $statement, ?Node $next): ?string
    {
        if (! $statement instanceof Expression || ! $statement->expr instanceof Assign || ! $next instanceof Return_) {
            return null;
        }

        $variable = $statement->expr->var;
        if (! $variable instanceof Variable || ! is_string($variable->name) || ! $next->expr instanceof Variable || $next->expr->name !== $variable->name) {
            return null;
        }

        return $variable->name;
    }

    /**
     * A local type assertion may be necessary even when the value is returned once.
     */
    private function hasTypeAnnotation(Node $statement, string $variable): bool
    {
        $comment = $statement->getDocComment()?->getText() ?? '';
        $comment = preg_replace('/^\h*\*\h?/m', '', $comment) ?? $comment;
        preg_match_all('/@var\s+(.+?)(?=\n[ \t]*@[a-zA-Z]|\*\/|$)/s', $comment, $annotations);
        foreach ($annotations[1] as $annotation) {
            $hasType = preg_match('/^(?:\??[a-zA-Z_\x80-\xff\\\\]|-?[0-9]|[\'"(]|\$this\b)/', $annotation) === 1;
            if ($hasType && $this->doesAnnotationTarget($annotation, $variable)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ignore quoted keys and callable parameter names when finding the annotation target.
     */
    private function doesAnnotationTarget(string $annotation, string $variable): bool
    {
        $typeSource = preg_replace('/\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"/s', "'literal'", $annotation) ?? $annotation;
        $depth = 0;
        foreach (PhpToken::tokenize('<?php '.$typeSource) as $token) {
            if ($token->id === T_VARIABLE && $depth === 0 && $token->text !== '$this') {
                return $token->text === '$'.$variable;
            }

            $depth += match ($token->text) {
                '<', '(', '[', '{'       => 1,
                '>', '>>', ')', ']', '}' => -strlen($token->text),
                default                  => 0,
            };
            if ($depth < 0) {
                return false;
            }
        }

        return $depth === 0;
    }

    /**
     * Collect statement lists within the current callable.
     *
     * @return iterable<array<Node>>
     */
    private function statementLists(Node $node): iterable
    {
        if ($node instanceof TryCatch && $node->finally instanceof Finally_) {
            return;
        }

        foreach (get_object_vars($node) as $name => $value) {
            if (is_array($value)) {
                if ($name === 'stmts') {
                    yield $value;
                }

                yield from $this->childLists($value);

                continue;
            }

            if ($value instanceof Node && ! $value instanceof FunctionLike) {
                yield from $this->statementLists($value);
            }
        }
    }

    /**
     * Visit children without crossing callable boundaries.
     *
     * @return iterable<array<Node>>
     */
    private function childLists(array $children): iterable
    {
        foreach ($children as $child) {
            if ($child instanceof Node && ! $child instanceof FunctionLike) {
                yield from $this->statementLists($child);
            }
        }
    }
}
