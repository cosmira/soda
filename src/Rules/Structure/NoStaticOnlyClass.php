<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;

/**
 * Reports classes whose declared surface is entirely static.
 */
#[Rule(
    label: 'Static-only classes:',
    advice: 'Name the operations as functions or constants, or keep instance state on the class; inherited and trait surfaces are not judged.',
)]
final class NoStaticOnlyClass extends Check
{
    /**
     * Declare an AST-only check.
     *
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Return the stable rule identifier.
     */
    #[\Override]
    public function id(): string
    {
        return 'no_static_only_class';
    }

    /**
     * Report named classes that offer no instance behavior of their own.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $facts): iterable
    {
        foreach ((new NodeFinder)->findInstanceOf($facts->nodes, Class_::class) as $class) {
            if (! $this->isStaticOnly($class)) {
                continue;
            }

            yield new Violation(
                rule: $this->id(),
                file: $facts->path,
                value: 1,
                threshold: 0,
                class: $class->name?->toString(),
                line: $class->name?->getStartLine(),
                message: sprintf('Class %s declares only static members. Name the operations as functions or constants, or keep instance state on the class.', $class->name?->toString()),
            );
        }
    }

    /**
     * Require at least one method, all methods static, and no inherited or trait surface.
     */
    private function isStaticOnly(Class_ $class): bool
    {
        $methods = $class->getMethods();

        return $class->name instanceof Identifier
            && ! $class->isAbstract()
            && ! $class->extends instanceof Name
            && $class->getTraitUses() === []
            && $methods !== []
            && $this->areAllStatic($methods)
            && $this->areAllStatic($class->getProperties());
    }

    /**
     * Require every member to declare itself static.
     *
     * @param list<ClassMethod|Property> $members
     */
    private function areAllStatic(array $members): bool
    {
        foreach ($members as $member) {
            if (! $member->isStatic()) {
                return false;
            }
        }

        return true;
    }
}
