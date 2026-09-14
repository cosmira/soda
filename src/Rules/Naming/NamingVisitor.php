<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Naming;

use function array_key_last;
use function array_pop;
use function array_values;

use Cosmira\Soda\Analysis\FactVisitor;
use Cosmira\Soda\Analysis\FileFacts;

use function in_array;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;

/**
 * Collects class and method names with param/return types for AvoidRedundantNaming rule.
 *
 * @internal
 *
 * @phpstan-import-type NamingFacts from FileFacts
 * @phpstan-import-type NamingType from FileFacts
 */
final class NamingVisitor extends FactVisitor
{
    /**
     * Defines magic methods used by this policy.
     */
    private const array MAGIC_METHODS = [
        '__construct', '__destruct', '__call', '__callStatic', '__get', '__set',
        '__isset', '__unset', '__sleep', '__wakeup', '__serialize', '__unserialize',
        '__toString', '__invoke', '__set_state', '__clone', '__debugInfo',
    ];

    /**
     * @psalm-var list<non-empty-string>
     */
    private array $classStack = [];

    /**
     * @var NamingFacts
     */
    private array $result = [
        'classes' => [],
        'methods' => [],
        'types'   => [],
    ];

    /**
     * @var array<string, NamingType>
     */
    private array $types = [];

    /**
     * Collect facts before traversing the children of this syntax node.
     */
    #[\Override]
    protected function doEnterNode(Node $node): void
    {
        $isType = $node instanceof Class_ || $node instanceof Trait_ || $node instanceof Interface_;
        if ($isType) {
            $this->enterType($node);

            return;
        }

        if ($node instanceof ClassMethod) {
            $this->enterMethod($node);

            return;
        }

        if ($node instanceof Function_) {
            $this->enterFunction($node);
        }
    }

    /**
     * Restore analysis state after traversing the children of this syntax node.
     */
    #[\Override]
    protected function doLeaveNode(Node $node): void
    {
        $shouldLeaveType = ($node instanceof Class_ || $node instanceof Trait_ || $node instanceof Interface_) && $this->classStack !== [];
        if ($shouldLeaveType) {
            array_pop($this->classStack);
        }
    }

    /**
     * Enter type using the current analysis state.
     */
    private function enterType(Class_|Trait_|Interface_ $node): void
    {
        $name = $node->namespacedName?->toString();
        $hasNoName = $name === null || $name === '';
        if ($hasNoName) {
            return;
        }

        $this->classStack[] = $name;

        $this->types[$name] = [
            'name'     => $name,
            'kind'     => $this->kindOf($node),
            'line'     => $node->getStartLine(),
            'inherits' => $this->inheritsOf($node),
            'methods'  => [],
        ];
        $isInterface = $node instanceof Interface_;

        if (! $isInterface) {
            $classes = $this->result['classes'];
            $classes[] = [
                'class' => $name,
                'line'  => $node->getStartLine(),
            ];
            $this->result['classes'] = $classes;
        }
    }

    /**
     * Enter method using the current analysis state.
     */
    private function enterMethod(ClassMethod $node): void
    {
        $methodName = $node->name->toString();
        if (in_array($methodName, self::MAGIC_METHODS, true)) {
            return;
        }

        $class = $this->currentClassName();
        $hasRecordedType = $class !== null && isset($this->types[$class]);
        if ($hasRecordedType) {
            $typeRow = $this->types[$class];
            $methods = $typeRow['methods'];
            $methods[] = $methodName;
            $typeRow['methods'] = $methods;
            $this->types[$class] = $typeRow;
        }

        $isInterfaceMember = $node->getAttribute('parent') instanceof Interface_;

        if ($isInterfaceMember) {
            return;
        }

        $methods = $this->result['methods'];
        $methods[] = NamingMethodFacts::fromClassMethod($node, $class);
        $this->result['methods'] = $methods;
    }

    /**
     * Enter function using the current analysis state.
     */
    private function enterFunction(Function_ $node): void
    {
        $methodResult = NamingMethodFacts::fromFunction($node);

        if ($methodResult === null) {
            return;
        }

        $methods = $this->result['methods'];
        $methods[] = $methodResult;
        $this->result['methods'] = $methods;
    }

    /**
     * @return NamingFacts
     */
    public function facts(): array
    {
        $this->result['types'] = array_values($this->types);

        return $this->result;
    }

    /**
     * Return current class name for the supplied analysis context.
     */
    private function currentClassName(): ?string
    {
        return $this->classStack !== [] ? $this->classStack[array_key_last($this->classStack)] : null;
    }

    /**
     * @return 'class'|'trait'|'interface'
     */
    private function kindOf(Class_|Trait_|Interface_ $node): string
    {
        return match (true) {
            $node instanceof Interface_ => 'interface',
            $node instanceof Trait_     => 'trait',
            default                     => 'class',
        };
    }

    /**
     * @return list<string>
     */
    private function inheritsOf(Class_|Trait_|Interface_ $node): array
    {
        return match (true) {
            $node instanceof Class_ => array_values(array_filter(
                [
                    $node->extends?->toString(),
                    ...array_map(static fn (Node\Name $name): string => $name->toString(), $node->implements),
                ],
                static fn (mixed $name): bool => is_string($name) && $name !== '',
            )),
            $node instanceof Interface_ => array_map(static fn (Node\Name $name): string => $name->toString(), $node->extends),
            default                     => [],
        };
    }
}
