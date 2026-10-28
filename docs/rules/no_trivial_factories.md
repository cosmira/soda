# no_trivial_factories

📝 Reports methods whose only work is constructing an object and forwarding all parameters unchanged.

💼 This rule is enabled in the ✅ `standard` config.

## Why

A factory that only forwards arguments adds another name for an existing
constructor. Consider direct construction when the method adds no creation logic
or published extension contract. Preserve argument names and type contracts.

## Examples

```php
// ❌ the method adds nothing to the constructor call
final class ReceiptFactory
{
    public function create(int $amount, string $currency): Receipt
    {
        return new Receipt($amount, $currency);
    }
}

$receipt = $factory->create(1000, 'EUR');
```

```php
// ✅ the caller constructs the object directly
final class Checkout
{
    public function complete(Cart $cart): Receipt
    {
        $receipt = new Receipt($cart->amount(), $cart->currency());
        $this->repository->save($receipt);

        return $receipt;
    }
}
```

```php
// ✅ accepted: the method transforms input before construction
final class ReceiptFactory
{
    public function create(int $amount, string $currency): Receipt
    {
        return new Receipt($amount * 100, strtoupper($currency));
    }
}
```

## Scope and exceptions

Observed virtual dispatch preserves a creation hook: another method calls the
factory through `$this` or `static`, and a descendant can replace its behavior.
A trait's methods can also be replaced by its consuming class, including calls
through `self`. Inlining construction would bypass that dispatch. These hooks
are recognized from declarations and call sites, without relying on factory names.

An actual call through an unchanged native class parameter can also establish a
creation hook across analyzed files. The initial call must resolve to an open
public instance method; its arguments must preserve the receiver binding. Callable
references, untyped receivers and a factory name alone do not establish this contract.

Final classes and final methods in ordinary classes close this extension point.
Ordinary private instance methods and fixed `self::` calls in classes do not
establish virtual dispatch. Late-static lookup can select a descendant's
same-named method even when the original declaration is private.

Package-local parent and trait declarations are located through the closest
`composer.json` PSR-4 mappings, including development mappings. An installed
`vendor` directory is optional. Installed dependency metadata still supplies
external contracts. Only declarations with the expected qualified name are used;
autoload scripts and source files are parsed as data and never executed.

An enclosing Composer manifest can also supply source roots when its `replace`
map explicitly names the closest nested package. This preserves hooks shared by
bundled components; an unrelated outer project or a mere `require` entry does
not establish that source relationship. Selector assignments after a call do not
invalidate the earlier dispatch; multiple preceding assignments remain ambiguous.

## Options

- `contracts` (default `[]`): list of qualified `Class::method` names whose published factory API must remain available; matching methods are exempt regardless of case. Framework-recognized creation hooks are exempt automatically.

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Structure\NoTrivialFactories;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new NoTrivialFactories(),
    ]);
```

## Related rules

- [no_trivial_delegating_classes](no_trivial_delegating_classes.md) — flags whole classes that only forward operations to a single dependency.
- [max_arguments](max_arguments.md) — bounds the parameter lists that transparent factories mirror.

