<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis\Control;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Normalizes class selectors and literal discriminator values used by type dispatch.
 */
final class DispatchedTypeValues
{
    /**
     * Resolve get_class and object::class to the object whose runtime type is selected.
     */
    public static function subject(Expr $selector): ?Expr
    {
        $isClassFetch = $selector instanceof Expr\ClassConstFetch && $selector->class instanceof Expr;
        if ($isClassFetch && $selector->name instanceof Node\Identifier && $selector->name->toLowerString() === 'class') {
            return $selector->class;
        }

        $isNamedCall = $selector instanceof Expr\FuncCall && $selector->name instanceof Node\Name;
        if (! $isNamedCall) {
            return null;
        }

        $name = strtolower($selector->name->toString());
        if ($name !== 'get_class' || count($selector->args) !== 1) {
            return null;
        }

        $argument = reset($selector->args);

        return $argument instanceof Node\Arg ? $argument->value : null;
    }

    /**
     * Only a named instanceof target contributes a statically identifiable type alternative.
     */
    public static function instanceClass(Expr\Instanceof_ $test): ?string
    {
        return $test->class instanceof Node\Name ? strtolower($test->class->toString()) : null;
    }

    /**
     * Preserve qualified class identity without treating other constants as class-name arms.
     */
    public static function className(?Node $value): ?string
    {
        $isNamedConstant = $value instanceof Expr\ClassConstFetch && $value->class instanceof Node\Name;
        if (! $isNamedConstant || ! $value->name instanceof Node\Identifier || $value->name->toLowerString() !== 'class') {
            return null;
        }

        return strtolower($value->class->toString());
    }

    /**
     * Preserve scalar type and constant identity for non-class discriminator selectors.
     */
    public static function discriminator(?Node $value): ?string
    {
        if ($value instanceof Node\Scalar\String_ || $value instanceof Node\Scalar\Int_) {
            return 'value:'.var_export($value->value, true);
        }

        $isConstant = $value instanceof Expr\ClassConstFetch && $value->class instanceof Node\Name;
        if ($isConstant && $value->name instanceof Node\Identifier) {
            return 'value:'.$value->class->toString().'::'.$value->name->toString();
        }

        return null;
    }
}
