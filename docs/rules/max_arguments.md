# max_arguments

📝 Limits the number of parameters a method or constructor may accept.

💼🚫 This rule is **not** enabled by default. Add it to your `soda.php` config to enable it.

## Why

A long parameter list forces every caller to know the order and meaning of many values. It often means the operation reaches into data that belongs somewhere else. The rule reports methods whose argument count exceeds the limit. Move behavior to the owner of its data, or group related arguments into a value object when they share meaning, operations, or an invariant. Wrapping arguments in a container solely to reduce the count does not help.

Signatures imposed by external APIs are exempt. These include native stream wrappers and framework overrides declared in `contracts`. A constructor forwarding to an inherited contract may match the parent arity. A readonly data constructor is accepted as a value object when its field count fits the configured `max_properties_per_class` policy.

## Examples

```php
// ❌ 5 arguments, each caller must know their order and meaning
final class InvoiceRenderer
{
    public function render(string $number, string $buyer, string $currency, int $amount, bool $late): string
    {
        // ...
    }
}

// ✅ related values share one meaning and travel together
final class InvoiceRenderer
{
    public function __construct(private readonly InvoiceNumber $number, private readonly Buyer $buyer) {}

    public function render(InvoiceAmount $amount): string
    {
        // ...
    }
}
```

## Options

- `limit` (default `3`): maximum accepted parameter count per method. A non-positive limit disables the check.
- `properties`: a `MaxPropertiesPerClass` instance whose limit decides when a readonly data constructor counts as an accepted value object instead of an argument violation.
- `contracts` (default `[]`): list of exact `Class::method` signatures imposed by external APIs; matching methods are exempt, regardless of case or leading backslash.

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Structure\MaxArguments;
use Cosmira\Soda\Rules\Structure\MaxPropertiesPerClass;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new MaxArguments(3, new MaxPropertiesPerClass(5)),
    ]);
```

## Related rules

- [max_properties_per_class](max_properties_per_class.md) — defines the property limit that decides when a readonly constructor is an accepted value object.
- [no_boolean_parameters](no_boolean_parameters.md) — boolean arguments are a common symptom of a parameter list doing several jobs.
- [no_trivial_factories](no_trivial_factories.md) — flags methods that only forward their parameters into a `new` expression.
