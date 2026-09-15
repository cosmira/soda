<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

/** Reports internal factories that only forward construction arguments. */
final class NoTrivialFactories extends Check
{
    /**
     * Report creation wrappers without changing their callers automatically.
     */
    public function checkFile(FileFacts $file): iterable
    {
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, Stmt\Class_::class) as $class) {
            $method = $this->factoryMethod($class);
            if (! $method instanceof ClassMethod) {
                continue;
            }

            $creation = $this->construction($method);
            if (! $creation instanceof New_ || ! $this->doesForwardParameters($method, $creation)) {
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

    /**
     * Find the sole creation method of an internal, stateless class.
     */
    private function factoryMethod(Stmt\Class_ $class): ?ClassMethod
    {
        if (! $this->isInternalClass($class) || ! $this->hasOnlyMethod($class)) {
            return null;
        }

        $methods = $class->getMethods();
        $method = reset($methods);

        return $this->isCreationMethod($method) ? $method : null;
    }

    /**
     * Limit recommendations to named internal final classes without a parent.
     */
    private function isInternalClass(Stmt\Class_ $class): bool
    {
        $internal = preg_match('/(?:^|\s|\*)@internal(?=\s|\*\/|$)/', $class->getDocComment()?->getText() ?? '') === 1;

        return $class->name instanceof Identifier && $class->isFinal() && ! $class->extends instanceof Name && $internal;
    }

    /**
     * Preserve class contracts, state and composition boundaries.
     */
    private function hasOnlyMethod(Stmt\Class_ $class): bool
    {
        $hasContract = $class->implements !== [] || $class->getTraitUses() !== [] || $class->attrGroups !== [];
        $hasState = $class->getProperties() !== [] || $class->getConstants() !== [];

        return ! $hasContract && ! $hasState && count($class->getMethods()) === 1;
    }

    /**
     * Recognize the public instance creation entry point without extra behavior.
     */
    private function isCreationMethod(ClassMethod $method): bool
    {
        $isPublicCreate = strtolower($method->name->toString()) === 'create' && $method->isPublic();
        $hasContract = $method->isStatic() || $method->byRef || $method->attrGroups !== [];

        return $isPublicCreate && ! $hasContract && count($method->stmts ?? []) === 1;
    }

    /**
     * Resolve a literal concrete class construction in the return statement.
     */
    private function construction(ClassMethod $method): ?New_
    {
        $statements = $method->stmts ?? [];
        $statement = reset($statements);
        $creation = $statement instanceof Stmt\Return_ ? $statement->expr : null;
        if (! $creation instanceof New_ || ! $creation->class instanceof Name) {
            return null;
        }

        $isRelative = in_array(strtolower($creation->class->toString()), ['self', 'static', 'parent'], true);

        return $isRelative ? null : $creation;
    }

    /**
     * Compare the parameter and argument sequences without reordering or duplication.
     */
    private function doesForwardParameters(ClassMethod $method, New_ $creation): bool
    {
        $parameters = array_map($this->parameterName(...), $method->params);
        $arguments = array_map($this->argumentName(...), $creation->args);
        $invalid = in_array(null, $parameters, true) || in_array(null, $arguments, true);

        return ! $invalid && $parameters === $arguments && count(array_unique($parameters)) === count($parameters);
    }

    /**
     * Reject defaults, references and attributes that carry additional contracts.
     */
    private function parameterName(Node\Param $parameter): ?string
    {
        $hasContract = $parameter->default instanceof Expr || $parameter->byRef || $parameter->variadic;
        $hasMetadata = $parameter->flags !== 0 || $parameter->attrGroups !== [];
        if ($hasContract || $hasMetadata || ! $parameter->var instanceof Expr\Variable) {
            return null;
        }

        return is_string($parameter->var->name) ? $parameter->var->name : null;
    }

    /**
     * Accept only a positional, unchanged variable argument.
     */
    private function argumentName(Node\Arg $argument): ?string
    {
        $hasBinding = $argument->name instanceof Identifier || $argument->unpack || $argument->byRef;
        if ($hasBinding || ! $argument->value instanceof Expr\Variable) {
            return null;
        }

        return is_string($argument->value->name) ? $argument->value->name : null;
    }

    /**
     * Identify this standard check in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'no_trivial_factories';
    }

    /**
     * Reuse the parsed AST without collecting metrics.
     *
     * @return list<string>
     */
    public function requiredAnalyses(): array
    {
        return [];
    }
}
