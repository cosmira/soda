<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use function is_string;
use function method_exists;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;

/**
 * @internal
 */
final class TellDontAskReceiverFingerprint
{
    /**
     * Describe a call receiver in a form that can be compared with later expressions.
     */
    private function callExprFingerprint(Expr $expr): ?string
    {
        return match (true) {
            $expr instanceof MethodCall || $expr instanceof NullsafeMethodCall => $this->methodReceiverFingerprint($expr),
            $expr instanceof StaticCall                                        => $expr->class instanceof Name ? $expr->class->toString() : null,
            default                                                            => null,
        };
    }

    /**
     * Describe a value expression so equivalent receivers can be recognized.
     */
    private function valueExprFingerprint(Expr $expr): ?string
    {
        return match (true) {
            $expr instanceof Variable && is_string($expr->name)  => '$'.$expr->name,
            $expr instanceof PropertyFetch                       => $this->propertyFingerprint($expr),
            $expr instanceof StaticPropertyFetch                 => $this->staticPropertyFingerprint($expr),
            default                                              => null,
        };
    }

    /**
     * Build the result from expr.
     */
    public function fromExpr(Expr $expr): ?string
    {
        $callFingerprint = $this->callExprFingerprint($expr);

        if ($callFingerprint !== null) {
            return $callFingerprint;
        }

        $valueFingerprint = $this->valueExprFingerprint($expr);

        if ($valueFingerprint !== null) {
            return $valueFingerprint;
        }

        return $expr instanceof ArrayDimFetch ? $this->fromExpr($expr->var) : null;
    }

    /**
     * Describe the receiver and method targeted by a statically identifiable call.
     */
    public function forCall(MethodCall|NullsafeMethodCall|StaticCall $expr): ?string
    {
        return match (true) {
            $expr instanceof MethodCall || $expr instanceof NullsafeMethodCall => $this->fromExpr($expr->var),
            $expr instanceof StaticCall                                        => $expr->class instanceof Name ? $expr->class->toString() : null,
        };
    }

    /**
     * Return a statically known callable name, or null for a computed target.
     */
    public function callName(MethodCall|NullsafeMethodCall|StaticCall $expr): ?string
    {
        return method_exists($expr->name, 'toString') ? $expr->name->toString() : null;
    }

    /**
     * Return method receiver fingerprint for the supplied analysis context.
     */
    private function methodReceiverFingerprint(MethodCall|NullsafeMethodCall $expr): ?string
    {
        $receiver = $this->fromExpr($expr->var);
        $method = $this->callName($expr);
        $isIncompleteCall = $receiver === null || $method === null;

        if ($isIncompleteCall) {
            return null;
        }

        return $receiver.'->'.$method.'()';
    }

    /**
     * Return property fingerprint for the supplied analysis context.
     */
    private function propertyFingerprint(PropertyFetch $expr): ?string
    {
        $hasNoName = ! method_exists($expr->name, 'toString');
        if ($hasNoName) {
            return null;
        }

        $receiver = $this->fromExpr($expr->var);

        return $receiver !== null ? $receiver.'->'.$expr->name->toString() : null;
    }

    /**
     * Describe a statically named property receiver for expression comparison.
     */
    private function staticPropertyFingerprint(StaticPropertyFetch $expr): ?string
    {
        $isDynamicCall = ! $expr->class instanceof Name || ! method_exists($expr->name, 'toString');
        if ($isDynamicCall) {
            return null;
        }

        return $expr->class->toString().'::$'.$expr->name->toString();
    }
}
