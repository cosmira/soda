# Soda

Soda checks how your PHP code is organized. It reports oversized methods,
nested workflows, repeated decisions, and naming problems with source locations
and refactoring advice. Use it alongside your formatter and PHPStan or Psalm.

## Get started

Requires PHP 8.2 or later and Composer.

```bash
composer require --dev cosmira/soda
vendor/bin/soda quality src/
```

Replace `src/` with your source directory. Without a `soda.php` configuration,
Soda runs its standard rules. Vendor directories are excluded automatically.
A passing check exits with `0`; quality violations or input and configuration
errors exit with `1`.

## Understand a finding

A shipping workflow can be valid PHP and still make the reader follow four
nested decisions:

```php
final class ShipOrders
{
    public function ship(array $orders): void
    {
        foreach ($orders as $order) {
            if ($order->isPaid()) {
                if ($order->isReady()) {
                    if ($order->hasAddress()) {
                        $order->ship();
                    }
                }
            }
        }
    }
}
```

Soda's nesting rule reports this method at its standard limit:

```text
src/ShipOrders.php
  × Line 10 (ShipOrders::ship)
    Control structure nesting: 4 (max 3)
    ↳ Return early for exceptional paths and extract the nested happy-path behavior into a named method.
```

A guard keeps the same order of checks and shipping behavior with less nesting:

```php
final class ShipOrders
{
    public function ship(array $orders): void
    {
        foreach ($orders as $order) {
            if (! $order->isPaid() || ! $order->isReady() || ! $order->hasAddress()) {
                continue;
            }

            $order->ship();
        }
    }
}
```

A finding identifies a violation of a selected policy, not necessarily a behavior
bug. [Review your first findings](docs/INSTALLATION.md#review-your-first-findings)
before deciding how to change your code or rule selection.

## Make it part of your project

[Choose a configuration](docs/SODA_PHP_CONFIG.md): keep the standard bundle in a
short `soda.php`, or generate editable rule objects with `vendor/bin/soda init`.
Then use `vendor/bin/soda` for the same check locally and in CI.

- [Installation](docs/INSTALLATION.md) covers your first check and its results.
- [Configuration](docs/SODA_PHP_CONFIG.md) covers paths, exclusions, and rules.
- [Continuous integration](docs/CI.md) covers exit status and JSON reports.
- [Documentation](docs/README.md) has the guides and rule reference.

## Contribute

Clone the repository and run `composer install`. Use `php soda` in place of
`vendor/bin/soda` inside this repository. Run `composer test` for behavioral tests
and `composer ci` for all project checks.

See [Adding a quality rule](docs/ADDING_A_QUALITY_RULE.md) to contribute a check.
For documentation changes, follow the [writing template](docs/WRITING_STYLE.md)
and run its Vale checks.

Soda is available under the [MIT license](LICENSE).
