# Complexity Metrics

Metrics that measure branches, control flow nesting, returns and exception handling.

**Config:** Complexity rules are plain objects in `soda.php`.

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Complexity\MaxBooleanConditions;
use Cosmira\Soda\Rules\Complexity\MaxControlNesting;
use Cosmira\Soda\Rules\Complexity\MaxCyclomaticComplexity;
use Cosmira\Soda\Rules\Complexity\MaxReturnStatements;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new MaxCyclomaticComplexity(10),
        new MaxControlNesting(3),
        new MaxReturnStatements(4),
        new MaxBooleanConditions(4),
    ]);
```

### max_cyclomatic_complexity

Counts branching: `if`, `elseif`, `switch`, `case`, `for`, `foreach`, `while`, `catch`, `&&`, `||`, `?:`.

| Config value | Strictness | Interpretation |
|--------------|------------|----------------|
| 5–8 | Strict | Simple methods only |
| 10–15 | Moderate | Acceptable |
| 20+ | Lenient | Complex logic allowed |

```php
// ❌ Bad (complexity 20+)
public function calculateDiscount(User $user, Order $order): float
{
    if ($user->isPremium()) {
        if ($order->total() > 1000) {
            return 0.2;
        }
        return 0.1;
    }
    if ($user->isNew()) {
        if ($order->items()->count() > 5) {
            return 0.05;
        }
        return 0;
    }
    if ($order->total() > 500 && $user->hasCoupon()) {
        return 0.15;
    }
    // ... 10 more branches
}
```

```php
// ✅ Good
public function calculateDiscount(User $user, Order $order): float
{
    return $this->strategies
        ->findFor($user)
        ->discount($order);
}
```

---

## Nesting Depth

### max_control_nesting

Maximum nesting level of `if`, `for`, `foreach`, `while`, `do`, `switch` and
`try`. Alternative branches (`elseif`, `else`, `catch`, `finally`) share the
level of their owning structure. A condition or loop inside such a branch adds
one level. Nested callable bodies are measured independently.

| Config value | Strictness |
|--------------|------------|
| 2–3 | Strict |
| 4 | Moderate |
| 5+ | Lenient |

```php
// ❌ Bad (4+ levels)
if ($a) {
    foreach ($b as $x) {
        if ($x->valid()) {
            switch ($x->type()) {
                case 1:
                    if ($y) {
                        // ...
                    }
                    break;
            }
        }
    }
}
```

```php
// ✅ Good
foreach ($this->filterValid($items) as $item) {
    $this->handle($item);
}
```

---

### max_try_catch_blocks (excessive exception handling)

**What it counts:** number of `try { … } catch (…) { … }` statements in a method or top-level function (each `try` counts once; `finally` does not add a second count). Nested `try` inside the same method counts separately. `try` inside closures passed to `array_map` and similar is **not** attributed to the enclosing method.

| Config value | Severity (default labels) | Meaning |
|--------------|---------------------------|---------|
| `2` | Warning at ≥3 blocks | Default: allow up to two `try/catch` per method |
| `0` | Disabled | Rule off |

```php
// ❌ Bad (3+ try/catch in one method when max_try_catch_blocks is 2)
function run() {
    try {} catch (\Exception $e) {}
    try {} catch (\Exception $e) {}
    try {} catch (\Exception $e) {}
}
```

```php
// ✅ Good: consolidate error handling or extract helpers with a single try/catch boundary
function run(): void
{
    try {
        $this->stepOne();
        $this->stepTwo();
    } catch (ProcessException $e) {
        $this->recover($e);
    }
}
```

---

### max_return_statements

Limits the number of `return` statements in a method or function. Multiple
returns can be fine, but too many exits often mean the method is mixing
validation, branching, and result construction.

```php
new MaxReturnStatements(4)
```

```php
// Good
public function status(): string
{
    if ($this->cancelled) {
        return 'cancelled';
    }

    return $this->paid ? 'paid' : 'open';
}
```

```php
// Bad
public function status(): string
{
    if ($this->cancelled) {
        return 'cancelled';
    }
    if ($this->expired) {
        return 'expired';
    }
    if ($this->refunded) {
        return 'refunded';
    }
    if ($this->paid) {
        return 'paid';
    }

    return 'open';
}
```

### max_boolean_conditions

Limits boolean operands in a single decision. Long condition chains hide named
business concepts and make branch changes risky.

```php
new MaxBooleanConditions(4)
```

```php
// Good
if ($order->canBeRefundedBy($user)) {
    $order->refund();
}
```

```php
// Bad
if ($order->isPaid() && ! $order->isExpired() && $user->canRefund() && $gateway->isOnline() && ! $order->isLocked()) {
    $order->refund();
}
```

---

## Optional exception checks

```php
new \Cosmira\Soda\Rules\Complexity\NoRedundantRethrow()
new \Cosmira\Soda\Rules\Complexity\NoEmptyFinallyBlocks()
```

### no_redundant_rethrow

Reports a final catch whose only executable statement
throws the exact bound variable. It excludes any earlier catch because deleting
it could expose an exception to a later handler. Logging, transformation,
conditional throws, closures and other statements are behavior and excluded.
Comments and empty statements do not execute. Catch-variable scope, destruction
of an overwritten binding and uses in `finally` can still matter: the diagnostic
requests review and does not claim automatic deletion is equivalent.

### no_empty_finally_blocks

Reports a finally containing only comments/empty
statements or nothing. With no catch, removing finally alone creates invalid PHP;
the diagnostic explicitly calls for considering the try body too. A return,
throw, assignment, call or nested try is executable and makes the block nonempty.

Both checks inspect the shared AST, including nested functions, traits and
anonymous classes, report the catch/finally keyword line, and use value 1 with
threshold 0 per finding. They are discoverable in `list:rules` as **opt-in**.
`RuleCatalog::standard()`, `RuleCatalog::checks()` and `init` do not enable them.
Select the named classes explicitly. There are no auto-fixes or suppressions.
Adaptations and license notices: [THIRD_PARTY_NOTICES.md](../THIRD_PARTY_NOTICES.md).

### max_cognitive_complexity

Optional `MaxCognitiveComplexity(15)` measures the documented **Soda PHP cognitive
v1** variant. It reports each contribution and retains independent nested callable
scopes. It is not a replacement for the existing standard or a claim of identical
Sonar scores. See [Research Metrics](RESEARCH_METRICS.md) for the exact formula,
PHP boundaries, limitations and corpus protocol.

### no_repeated_compound_conditions

Enabled by default: `new NoRepeatedCompoundConditions(minMethods: 3)` reports
an identical compound condition in at least three distinct methods of one class.
The diagnostic maximum is 2 methods with the default minimum of 3. Values below
2 for `minMethods` throw `InvalidArgumentException`.

For example, three methods containing `if ($this->active && ! $this->suspended)`
can call a private `canRenew()` predicate when all three express the same decision.
The predicate must be reevaluated at each call, not cached across state changes.

Declared instance-property reads, literals, named constants, `!`, `===`, `!==`,
`&&` and `||` are recognized. Formatting is ignored; operand order and literal
values remain significant. Immediately preceding local snapshots and direct alias chains
are normalized; intervening statements end that snapshot scope. Known inherited and trait
methods are composed across files. Aliases of one body count once. Private state and
relative `self`/`parent` constants retain lexical identity; late-bound `static` constants
belong to the effective receiver. Calls and arbitrary computed locals remain excluded.
Unrelated inheritance, traits, magic methods or hooked properties do not suppress checking
ordinary declared properties of the class.
A property with its own hook remains outside the recognized read vocabulary. Conditions inside nested callables belong to their
own scope. Repetition within one method counts once. Independent decisions should
not be merged merely because their expressions currently match.
The [original RFC](rfcs/explicit-behavior-rules.md) records the earlier research scope;
the behavior above describes the strengthened check.
