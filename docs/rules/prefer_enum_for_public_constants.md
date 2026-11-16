# prefer_enum_for_public_constants

📝 Reports classes declaring more than two public constants and recommends an enum.

💼 This rule is enabled in the ✅ `standard` config.

## Why

A class with several public constants often represents a fixed set of choices. An enum makes those choices a type that callers can accept directly.

The rule reports one finding per class when it declares at least three public constants. Implicit public visibility and multiple constants in one declaration count individually. Private and protected constants do not count. Inherited and trait-provided constants are not expanded. Interfaces, traits, and enums are excluded; anonymous classes are included.

This is a design recommendation, not an automatic conversion. Unrelated constants and values that cannot be enum cases need manual review. Converting string constants to enum cases changes callers; use backed enum values where a protocol requires strings.

## Examples

```php
// ❌ A class exposes a set of protocol choices.
final class Protocol
{
    public const IMAP = 'IMAP';
    public const MAPI = 'MAPI';
    public const HTTP = 'HTTP';
}

// ✅ The choices have their own type.
enum Protocol: string
{
    case IMAP = 'IMAP';
    case MAPI = 'MAPI';
    case HTTP = 'HTTP';
}
```

## Configuration

The maximum is two public constants. There are no constructor options.

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Structure\PreferEnumForPublicConstants;

return Soda::configure()
    ->withPaths([__DIR__.'/src'])
    ->with([
        new PreferEnumForPublicConstants(),
    ]);
```

## Related rules

- [max_public_methods](max_public_methods.md) limits the public behavior exposed by a class.
