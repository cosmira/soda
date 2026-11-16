<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use Cosmira\Soda\Analysis\CallableIdentity;
use Cosmira\Soda\Analysis\Complexity\StatementLists;
use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

#[Rule(
    label: 'Unnecessary nullable collections:',
    advice: 'Remove null from the return type when every proven return produces a collection.',
)]
final class NoNullableCollections extends Check
{
    /**
     * AST link used to separate nested callable scopes.
     */
    private const PARENT = 'parent';

    /**
     * Native collection supertype, shared by arrays and Traversable objects.
     */
    private const ITERABLE = 'iterable';

    /**
     * Laravel collection interface, including eager, lazy and Eloquent collections.
     */
    private const ENUMERABLE = 'illuminate\\support\\enumerable';

    /**
     * Only known collection relationships are inferred; project classes are never autoloaded.
     */
    private const PARENTS = [
        'array'                                      => self::ITERABLE,
        self::ITERABLE                               => '',
        'arrayiterator'                              => self::ITERABLE,
        'emptyiterator'                              => self::ITERABLE,
        self::ENUMERABLE                             => self::ITERABLE,
        'illuminate\\support\\collection'            => self::ENUMERABLE,
        'illuminate\\database\\eloquent\\collection' => 'illuminate\\support\\collection',
        'illuminate\\support\\lazycollection'        => self::ENUMERABLE,
    ];

    /**
     * Identify collection signatures whose bodies cannot return null.
     */
    #[\Override]
    public function id(): string
    {
        return 'no_nullable_collections';
    }

    /**
     * Report only closed callable contracts with conclusive collection-return evidence.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, FunctionLike::class) as $scope) {
            $type = $this->collectionType($scope->getReturnType());
            if ($type === null || ! $this->canNarrow($scope) || ! $this->isProven($scope, $type)) {
                continue;
            }

            yield new Violation(
                rule: $this->id(), file: $file->path, value: 1, threshold: 0,
                method: $scope instanceof ClassMethod ? CallableIdentity::name($scope) : null,
                line: $scope->getReturnType()->getStartLine(),
                message: 'Remove null from this collection return type; every proven return produces a collection.',
            );
        }
    }

    /**
     * Additional union members can encode a different result protocol and are outside this check.
     */
    private function collectionType(?Node $type): ?string
    {
        if (! $type instanceof Node) {
            return null;
        }

        $fields = get_object_vars($type);
        $name = '';
        if ($type->getType() === 'NullableType') {
            $name = $this->typeName($fields['type']);
        } elseif ($type->getType() === 'UnionType' && count($fields['types']) === 2) {
            $names = array_map($this->typeName(...), $fields['types']);
            if (in_array('null', $names, true)) {
                $name = $names[0] === 'null' ? $names[1] : $names[0];
            }
        }

        return array_key_exists($name, self::PARENTS) ? $name : null;
    }

    /**
     * NameResolver supplies fully qualified names for imported collection classes.
     */
    private function typeName(Node $type): string
    {
        return $type instanceof Name || $type instanceof Identifier ? $type->toLowerString() : '';
    }

    /**
     * Open extension points can intentionally reserve nullable results for overriding methods.
     */
    private function canNarrow(FunctionLike $scope): bool
    {
        if ($scope->returnsByRef() || $this->isGenerator($scope)) {
            return false;
        }

        if (! $scope instanceof ClassMethod) {
            return true;
        }

        $owner = $scope->getAttribute(self::PARENT);

        return $owner instanceof Class_ && ($scope->isPrivate() || $scope->isFinal() || $owner->isFinal());
    }

    /**
     * Even unreachable yield changes the callable into a generator; nested yields do not.
     */
    private function isGenerator(FunctionLike $scope): bool
    {
        return (new NodeFinder)->findFirst($scope->getStmts() ?? [], fn (Node $node): bool => in_array($node->getType(), ['Expr_Yield', 'Expr_YieldFrom'], true) && $this->isInScope($node, $scope)) instanceof Node;
    }

    /**
     * Unknown returns, generators and control transfers invalidate the proof rather than guessing.
     */
    private function isProven(FunctionLike $scope, string $type): bool
    {
        $body = $scope->getStmts();
        if ($body === null) {
            return false;
        }

        $body = $this->reachableBody($body);
        $isArrow = $scope->getType() === 'Expr_ArrowFunction';
        $hasReturn = $isArrow && $this->isCollection(get_object_vars($scope)['expr'], $type);
        $nodes = $isArrow ? [get_object_vars($scope)['expr']] : $body;
        $flow = (new NodeFinder)->find($nodes, fn (Node $node): bool => in_array($node->getType(), [
            'Stmt_Return', 'Stmt_Goto', 'Stmt_TryCatch', 'Stmt_Break', 'Stmt_Continue',
        ], true) && $this->isInScope($node, $scope));
        foreach ($flow as $node) {
            if ($node->getType() !== 'Stmt_Return') {
                return false;
            }

            $value = get_object_vars($node)['expr'];
            if (! $value instanceof Expr || ! $this->isCollection($value, $type)) {
                return false;
            }

            $hasReturn = true;
        }

        return $hasReturn && ($isArrow || StatementLists::doesEndWithExit($body));
    }

    /**
     * Statements after an unconditional exit cannot contribute a return value.
     *
     * @param list<Node\Stmt> $body
     *
     * @return list<Node\Stmt>
     */
    private function reachableBody(array $body): array
    {
        foreach ($body as $index => $statement) {
            if (StatementLists::isExit($statement)) {
                return array_slice($body, 0, $index + 1);
            }
        }

        return $body;
    }

    /**
     * Nested closures and methods own their own return values and generator markers.
     */
    private function isInScope(Node $node, FunctionLike $scope): bool
    {
        $owner = $node->getAttribute(self::PARENT);
        while ($owner instanceof Node && ! $owner instanceof FunctionLike) {
            $owner = $owner->getAttribute(self::PARENT);
        }

        return $owner === $scope;
    }

    /**
     * Calls and variable bindings require more evidence than a literal, cast or known constructor.
     */
    private function isCollection(Expr $expression, string $type): bool
    {
        $fields = get_object_vars($expression);

        return match ($expression->getType()) {
            'Expr_Array', 'Expr_Cast_Array' => $this->isSubtype('array', $type),
            'Expr_New'                      => $fields['class'] instanceof Name
                && $fields['class']->toLowerString() !== self::ENUMERABLE
                && $this->isSubtype($fields['class']->toLowerString(), $type),
            'Expr_Ternary' => $this->areCollections([$fields['if'] ?? $fields['cond'], $fields['else']], $type),
            'Expr_Match'   => $this->areCollections(array_map(fn ($arm): Expr => $arm->body, $fields['arms']), $type),
            default        => false,
        };
    }

    /**
     * Every alternative must independently produce the declared collection kind.
     *
     * @param list<Expr> $expressions
     */
    private function areCollections(array $expressions, string $type): bool
    {
        foreach ($expressions as $expression) {
            if (! $this->isCollection($expression, $type)) {
                return false;
            }
        }

        return $expressions !== [];
    }

    /**
     * Follow the bounded native/Laravel collection relationships without executing project code.
     */
    private function isSubtype(string $actual, string $expected): bool
    {
        $candidate = $actual;
        while (isset(self::PARENTS[$candidate])) {
            if ($candidate === $expected) {
                return true;
            }

            $candidate = self::PARENTS[$candidate];
        }

        return false;
    }
}
