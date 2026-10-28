# repeated_foreign_state_update

📝 Finds the same arithmetic update of another object's numeric state repeated in several consumers and suggests naming it as an operation on the owner.

💼 This rule is enabled in the ✅ `standard` config.

## Why

When several consumers compute `$wallet->setBalance($wallet->balance() - $amount)`, the change formula lives outside the object that owns the state, so adjusting it means finding and editing every consumer. The rule reports such repeated arithmetic as a review signal and advises introducing a named owner operation (for example `debit($amount)`); a concern (trait) can organize related owner behavior.

The detector fingerprints the arithmetic expression exactly. Supported expressions contain numeric literals, stable numeric parameters, and zero-argument getters of the same field. Supported operators are `+`, `-`, `*`, `/`, `%`, `**`, and unary `+` and `-`.

It requires `minConsumers` distinct named methods or functions feeding the result to a proven setter of one local, uncomposed owner type. The owner must have one mutable `int`, `float`, or `int|float` field. To exclude unresolved overrides, the owner class or both accessors must be final. The setter must directly assign its single numeric input to that field, optionally followed by `return $this`.

Receiver names and input names are normalized. Operand order, literal values, input sharing, and numeric contracts remain significant. Proven preceding direct aliases share receiver identity. Repeats within one consumer count once; different formulas and fields form separate findings.

## Examples

```php
// ❌
function checkout(Wallet $wallet, int $amount): void
{
    $wallet->setBalance($wallet->balance() - $amount);
}

function renewal(Wallet $wallet, int $amount): void
{
    $wallet->setBalance($wallet->balance() - $amount);
}

function withdrawal(Wallet $wallet, int $amount): void
{
    $wallet->setBalance($wallet->balance() - $amount);
}

// ✅
final class Wallet
{
    private int $balance = 100;

    public function debit(int $amount): void
    {
        $this->balance -= $amount;
    }
}

function checkout(Wallet $wallet, int $amount): void
{
    $wallet->debit($amount);
}
```

Updates over a different receiver or field are not reported. A plain copy such as `setBalance($wallet->balance())`, a literal write, or a getter with side effects is also accepted. Computed locals, casts, nullsafe or dynamic access, and reassigned receivers or inputs remain outside the rule.

The ❌ shape requires the `Wallet` owner to be declared in the analyzed paths with the contract shown in the ✅ section. It must have one mutable numeric field, a proven zero-argument getter, and a direct-assignment setter. The class or both accessors must be final. The three consumers shown are the minimum at the default `minConsumers`; together with the owner declaration, they produce the report.

## Options

| Option | Default | Description |
| --- | --- | --- |
| `minConsumers` | `3` | Minimum independent consumers repeating the same update. Must be at least `2`. |

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Design\RepeatedForeignStateUpdate;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new RepeatedForeignStateUpdate(minConsumers: 3),
    ]);
```

## Related rules

- [repeated_foreign_state_decision](repeated_foreign_state_decision.md) — the same distribution problem for boolean questions about foreign state instead of arithmetic changes to it.
- [fragmented_owner_behavior](fragmented_owner_behavior.md) — whole owner operations spread across classes rather than a single update repeated.
- [max_ask_then_tell_patterns](max_ask_then_tell_patterns.md) — bounds asking a foreign object for state to change it, of which this is a repeated form.
