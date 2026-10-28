# max_variable_name_words

Reports local variable and parameter names that exceed a chosen word count.

This rule is opt-in and is not registered in the built-in catalog. It does not
appear in `list:rules`; configure its class directly. Its violations can fail
the quality check when enabled.

## Why

A compound local name can be a useful prompt to inspect context, units, or
missing domain structure. A word-count limit alone cannot decide whether the
name is wrong. Use this policy only when your team has deliberately selected it.

## Examples and scope

This rule is intentionally opt-in. Standard Soda lets local context determine
whether `$orderNumber` is clearer than `$number`; a fixed word count cannot
make that decision reliably. Enable it only when a team deliberately adopts a
mechanical naming policy.

Compound scalar names often reveal missing structure. `$fileName` can mean that
the scope already contains both `$file` and `$name`; `$temperatureInFahrenheit`
has to carry its unit because a bare float cannot protect that invariant.

```php
new MaxVariableNameWords(maxWords: 1)
```

```php
// Bad — the variable name is acting as a weak type.
$temperatureInFahrenheit = 98.6;
```

Prefer a value object whose constructors make the unit explicit and whose
internal representation is canonical:

```php
final class Temperature
{
    public static function fromCelsius(float $degrees): self
    {
        return new self($degrees);
    }

    public static function fromFahrenheit(float $degrees): self
    {
        $celsius = ($degrees - 32) * 5 / 9;

        return new self($celsius);
    }

    private function __construct(
        public float $valueInCelsius,
    ) {}
}
```

The rule checks local variables and ordinary parameters. It deliberately does
not check class names or object properties: implementation names, proper names,
acronyms, and canonical state inside a focused type can be legitimate.
Consecutive capitals such as `HTTP` count as one word. For a legitimate local
proper name or implementation reference, use an exact exception:

```php
new MaxVariableNameWords(
    maxWords: 1,
    ignore: ['openAI', 'redisCache'],
)
```

Existing projects can start with `maxWords: 2` or `3` and ratchet the limit
down. A violation is a design smell to inspect, not proof that every compound
name is wrong.

## Configuration

```php
<?php

use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Naming\MaxVariableNameWords;

return Soda::configure()
    ->withPaths([__DIR__.'/src'])
    ->with([new MaxVariableNameWords(maxWords: 2, ignore: ['openAI'])]);
```

This configuration selects only this rule. See [Configuration](../SODA_PHP_CONFIG.md)
for the standard bundle and editable rule lists.
