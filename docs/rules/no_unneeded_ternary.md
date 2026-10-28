# no_unneeded_ternary

📝 Reports full ternaries that restate a boolean conversion or short-circuit decision.

💼 This rule is enabled in the ✅ `standard` config.

## Why

A ternary that only restates a boolean result hides the decision in extra
branches. Use a boolean conversion, negation, or logical operator when it
preserves the result and evaluation order.

## Examples

```php
// ❌ the ternary restates the condition
$result = isReady() ? true : false;
$inverse = $unknown ? false : true;

// ✅ conversion and negation say it directly
$result = (bool) isReady();
$inverse = ! $unknown;
```

```php
// ✅ accepted: non-literal branches and matching literals
$price = $unknown ? 1 : 0;
$state = $unknown ? true : true;
```

```php
// ❌ a literal branch hides a short-circuit decision
$present = $enabled ? isset($value) : false;
$missing = $enabled ? false : isset($value);

// ✅ the logical operators preserve both result type and branch effects
$present = $enabled && isset($value);
$missing = ! $enabled && isset($value);

// ✅ accepted: the selected branch's type is unknown
$result = $enabled ? $value : false;
$result = $enabled ? true : isReady();
```

## Scope and exceptions

`$ready ? true : false` restates the condition itself; a cast or a negation says the same thing. For full ternaries selecting opposite `true`/`false` literals, the rule suggests `(bool) (condition)` for `$cond ? true : false` and `! (condition)` for `$cond ? false : true`. Matching literals on both sides stay accepted. Preserve boolean conversion and evaluate the condition once; drop the cast only when the condition is known to return bool.

With one literal boolean branch, a logical operator can express the decision when
the other branch already returns bool. For example, `$condition ? true : isset($value)`
becomes `($condition) || isset($value)`, while `$condition ? isset($value) : false`
becomes `($condition) && isset($value)`. Inverse forms negate the condition.
The condition is evaluated once and the non-literal branch retains its original
short-circuit evaluation. Keep parentheses around the original expressions and
use `&&` or `||`, rather than PHP's low-precedence `and` or `or`.

Syntax proves boolean results for negation, boolean casts, `isset`, `empty`,
`instanceof`, equality and ordering comparisons, and logical operations. Arbitrary
variables, calls, ternaries and match expressions are not inferred to be bool.
The integer-valued spaceship operator remains accepted, as do shorthand ternaries.
An unknown branch may return a meaningful non-boolean value and cannot be replaced
by a logical operator without additional proof.

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Complexity\NoUnneededTernary;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new NoUnneededTernary,
    ]);
```

## Related rules

- [no_nested_ternary](no_nested_ternary.md) — flags decisions hidden inside the result branches of a ternary.
- [no_identical_branches](no_identical_branches.md) — flags ternary branches that produce the same expression.
- [boolean_methods_without_prefix](boolean_methods_without_prefix.md) — keeps the predicates feeding such conditions clearly named.
