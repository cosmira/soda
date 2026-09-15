<?php

declare(strict_types=1);

namespace Cosmira\Soda\Research\ExplicitBehavior;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/** Research candidate: not part of the shipped rule catalog. */
final class NoTrivialFactories extends Check
{
    public function id(): string
    {
        return 'no_trivial_factories';
    }

    public function requiredAnalyses(): array
    {
        return [];
    }

    public function checkFile(FileFacts $file): iterable
    {
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, Stmt\Class_::class) as $class) {
            if (! $this->eligible($class)) {
                continue;
            }

            $method = $class->getMethods()[0];
            $statement = $method->stmts[0] ?? null;
            $creation = $statement instanceof Stmt\Return_ ? $statement->expr : null;
            if (! $creation instanceof Expr\New_ || ! $creation->class instanceof Node\Name
                || in_array(strtolower($creation->class->toString()), ['self', 'static', 'parent'], true)
                || ! $this->forwards($method, $creation)) {
                continue;
            }

            $name = isset($class->namespacedName) ? $class->namespacedName->toString() : $class->name->toString();
            yield new Violation(
                rule: $this->id(), file: $file->path, value: 1, threshold: 0,
                method: $method->name->toString(), class: $name, line: $method->getStartLine(),
                message: sprintf('%s::create() only constructs %s and forwards all parameters unchanged. Consider constructing %s at the call site.',
                    $name, $creation->class->toString(), $creation->class->toString()),
            );
        }
    }

    private function eligible(Stmt\Class_ $class): bool
    {
        if ($class->name === null || ! $class->isFinal() || $class->extends !== null
            || $class->implements !== [] || $class->getTraitUses() !== [] || $class->attrGroups !== []
            || $class->getProperties() !== [] || $class->getConstants() !== []
            || preg_match('/(?:^|\s|\*)@internal(?=\s|\*\/|$)/', $class->getDocComment()?->getText() ?? '') !== 1
            || count($class->getMethods()) !== 1) {
            return false;
        }

        $method = $class->getMethods()[0];

        return strtolower($method->name->toString()) === 'create' && $method->isPublic()
            && ! $method->isStatic() && ! $method->byRef && $method->attrGroups === []
            && count($method->stmts ?? []) === 1;
    }

    private function forwards(Stmt\ClassMethod $method, Expr\New_ $creation): bool
    {
        if (count($method->params) !== count($creation->args)) {
            return false;
        }

        $names = [];
        foreach ($method->params as $index => $parameter) {
            $argument = $creation->args[$index];
            if ($parameter->default !== null || $parameter->byRef || $parameter->variadic
                || $parameter->flags !== 0 || $parameter->attrGroups !== []
                || ! $parameter->var instanceof Expr\Variable || ! is_string($parameter->var->name)
                || isset($names[$parameter->var->name])
                || ! $argument instanceof Node\Arg || $argument->name !== null || $argument->unpack || $argument->byRef
                || ! $argument->value instanceof Expr\Variable || $argument->value->name !== $parameter->var->name) {
                return false;
            }
            $names[$parameter->var->name] = true;
        }

        return true;
    }
}
