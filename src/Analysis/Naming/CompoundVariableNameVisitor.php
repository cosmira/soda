<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis\Naming;

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
 * @phpstan-type Scope array{key: string, class: ?string, method: ?string}
 *
 * @internal
 */
final class CompoundVariableNameVisitor extends FactVisitor
{
    /**
     * Superglobal names whose entries never originate from local declarations.
     */
    private const SUPERGLOBALS = [
        'GLOBALS', '_SERVER', '_GET', '_POST', '_FILES', '_COOKIE',
        '_SESSION', '_REQUEST', '_ENV',
    ];

    /**
     * Compound variable names recorded with their source and callable context.
     *
     * @var list<CompoundVariableNameOccurrence>
     */
    private array $occurrences = [];

    /**
     * Previously recorded identifiers used to avoid duplicate naming findings.
     *
     * @var array<string, true>
     */
    private array $seen = [];

    /**
     * Promoted property identifiers tracked alongside ordinary variable occurrences.
     *
     * @var array<int, true>
     */
    private array $promotedProperties = [];

    /**
     * Saved callable context restored after traversing nested scopes.
     *
     * @var list<Scope>
     */
    private array $scopeStack = [];

    /**
     * Saved class context restored after traversing nested declarations.
     *
     * @var list<string|null>
     */
    private array $classStack = [];

    /**
     * Callable context currently being traversed.
     *
     * @var Scope
     */
    private array $scope = ['key' => 'file', 'class' => null, 'method' => null];

    /**
     * Class owning the declarations currently being traversed.
     */
    private ?string $class = null;

    /**
     * Monotonic counter giving every visited scope a stable occurrence key.
     */
    private int $scopeSequence = 0;

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

        if ($previous !== null) {
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
        $this->scope = [
            'key'    => 'callable@'.$node->getStartLine().'#'.$this->scopeSequence,
            'class'  => $this->class,
            'method' => $this->methodName($node),
        ];
    }

    /**
     * Return the lexical callable name when the syntax declares one.
     */
    private function methodName(Node $node): ?string
    {
        return match (true) {
            $node instanceof ClassMethod, $node instanceof Function_ => $node->name->toString(),
            default                                                  => null,
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

        $words = $this->words($variable->name);
        $isSingleWord = count($words) < 2;
        if ($isSingleWord) {
            return;
        }

        $key = $this->scope['key'].'$'.$variable->name;
        if (isset($this->seen[$key])) {
            return;
        }

        $this->seen[$key] = true;
        $this->occurrences[] = new CompoundVariableNameOccurrence(
            $variable->name,
            $words,
            $variable->getStartLine(),
            $this->scope['class'],
            $this->scope['method'],
        );
    }

    /**
     * Split camelCase and snake_case while keeping consecutive capitals as one acronym.
     *
     * @return list<non-empty-string>
     */
    private function words(string $name): array
    {
        $words = [];
        $segments = preg_split('/_+/', trim($name, '_'));
        if ($segments === false) {
            return [];
        }

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            $matches = [];
            preg_match_all('/[A-Z]+(?=[A-Z][a-z]|$)\d*|[A-Z]?[a-z]+\d*/', $segment, $matches);
            $segmentWords = array_shift($matches) ?? [];
            if ($segmentWords === []) {
                $segmentWords = [$segment];
            }

            foreach ($segmentWords as $word) {
                $isNonemptyWord = is_string($word) && $word !== '';
                if ($isNonemptyWord) {
                    $words[] = strtolower($word);
                }
            }
        }

        return $words;
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
