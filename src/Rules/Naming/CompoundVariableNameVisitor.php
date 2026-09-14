<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Naming;

use Cosmira\Soda\Analysis\FactVisitor;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;

/**
 * Collects each compound local variable or parameter once per lexical scope.
 *
 * Object properties are intentionally excluded: their owning type supplies
 * context that a free scalar does not have.
 *
 * @internal
 */
final class CompoundVariableNameVisitor extends FactVisitor
{
    /**
     * Defines superglobals used by this policy.
     */
    private const array SUPERGLOBALS = [
        'GLOBALS', '_SERVER', '_GET', '_POST', '_FILES', '_COOKIE',
        '_SESSION', '_REQUEST', '_ENV',
    ];

    /**
     * @var list<CompoundVariableNameOccurrence>
     */
    private array $occurrences = [];

    /**
     * @var array<string, true>
     */
    private array $seen = [];

    /**
     * @var array<int, true>
     */
    private array $promotedProperties = [];

    /**
     * @var list<CompoundVariableScope>
     */
    private array $scopeStack = [];

    /**
     * @var list<string|null>
     */
    private array $classStack = [];

    /**
     * Stores scope for this analysis instance.
     */
    private CompoundVariableScope $scope;

    /**
     * Stores class for this analysis instance.
     */
    private ?string $class = null;

    /**
     * Stores scope sequence for this analysis instance.
     */
    private int $scopeSequence = 0;

    /**
     * Starts collection in the file-level lexical scope.
     */
    public function __construct()
    {
        $this->scope = new CompoundVariableScope('file');
    }

    /**
     * Collect facts before traversing the children of this syntax node.
     */
    #[\Override]
    protected function doEnterNode(Node $node): void
    {
        match (true) {
            $node instanceof ClassLike                         => $this->enterClass($node),
            $this->isCallable($node)                           => $this->enterCallable($node),
            $node instanceof Param && $node->flags !== 0       => $this->markPromotedProperty($node),
            $node instanceof Variable                          => $this->recordVariable($node),
            default                                            => null,
        };
    }

    /**
     * Restore analysis state after traversing the children of this syntax node.
     */
    #[\Override]
    protected function doLeaveNode(Node $node): void
    {
        $this->leaveCallableIfNeeded($node);

        if ($node instanceof ClassLike) {
            $this->leaveClass();
        }
    }

    /**
     * Returns unique compound names collected from local variables and parameters.
     *
     * @return list<CompoundVariableNameOccurrence>
     */
    public function occurrences(): array
    {
        return $this->occurrences;
    }

    /**
     * Enter class using the current analysis state.
     */
    private function enterClass(ClassLike $class): void
    {
        $this->classStack[] = $this->class;
        $this->class = isset($class->namespacedName)
            ? $class->namespacedName->toString()
            : $class->name?->toString();
    }

    /**
     * Leave callable using the current analysis state.
     */
    private function leaveCallable(): void
    {
        $previous = array_pop($this->scopeStack);

        if ($previous instanceof CompoundVariableScope) {
            $this->scope = $previous;
        }
    }

    /**
     * Leave callable if needed using the current analysis state.
     */
    private function leaveCallableIfNeeded(Node $node): void
    {
        $isCallable = $node instanceof ClassMethod
            || $node instanceof Function_
            || $node instanceof Closure
            || $node instanceof ArrowFunction;
        if ($isCallable) {
            $this->leaveCallable();
        }
    }

    /**
     * Leave class using the current analysis state.
     */
    private function leaveClass(): void
    {
        $previous = array_pop($this->classStack);
        $this->class = is_string($previous) ? $previous : null;
    }

    /**
     * Determine whether callable applies to the supplied input.
     */
    private function isCallable(Node $node): bool
    {
        return $node instanceof ClassMethod
            || $node instanceof Function_
            || $node instanceof Closure
            || $node instanceof ArrowFunction;
    }

    /**
     * Enter callable using the current analysis state.
     */
    private function enterCallable(Node $node): void
    {
        $this->scopeStack[] = $this->scope;
        $this->scopeSequence++;
        $this->scope = new CompoundVariableScope(
            'callable@'.$node->getStartLine().'#'.$this->scopeSequence,
            $this->class,
            $this->methodName($node),
        );
    }

    /**
     * Return the lexical callable name when the syntax declares one.
     */
    private function methodName(Node $node): ?string
    {
        return match (true) {
            $node instanceof ClassMethod => $node->name->toString(),
            $node instanceof Function_   => $node->name->toString(),
            default                      => null,
        };
    }

    /**
     * Mark promoted property using the current analysis state.
     */
    private function markPromotedProperty(Param $parameter): void
    {
        if ($parameter->var instanceof Variable) {
            $this->promotedProperties[spl_object_id($parameter->var)] = true;
        }
    }

    /**
     * Record variable in the current analysis scope.
     */
    private function recordVariable(Variable $variable): void
    {
        $shouldSkip = ! is_string($variable->name) || $this->shouldIgnore($variable);
        if ($shouldSkip) {
            return;
        }

        $words = CompoundIdentifierWords::split($variable->name);
        $isSingleWord = count($words) < 2;
        if ($isSingleWord) {
            return;
        }

        $key = $this->scope->key.'$'.$variable->name;
        if (isset($this->seen[$key])) {
            return;
        }

        $this->seen[$key] = true;
        $this->occurrences[] = new CompoundVariableNameOccurrence(
            $variable->name,
            $words,
            $variable->getStartLine(),
            $this->scope->class,
            $this->scope->method,
        );
    }

    /**
     * Decide whether to ignore under the current policy.
     */
    private function shouldIgnore(Variable $variable): bool
    {
        return $variable->name === 'this'
            || in_array($variable->name, self::SUPERGLOBALS, true)
            || isset($this->promotedProperties[spl_object_id($variable)]);
    }
}
