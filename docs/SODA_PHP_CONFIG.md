# Config: `soda.php`

Soda has one project config file: `soda.php` in the project root.
Run `vendor/bin/soda` to check its configured paths. Without a config,
`vendor/bin/soda quality src/` uses the standard rules.

To generate an expanded starting configuration, run:

```bash
vendor/bin/soda init
```

It creates an editable PHP file with the built-in rule catalog expanded into
plain rule objects. A shortened example:

```php
<?php

declare(strict_types=1);

use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Complexity\NoAssignmentInCondition;
use Cosmira\Soda\Rules\Structure\MaxFileLoc;
use Cosmira\Soda\Rules\Structure\MaxLineLength;
use Cosmira\Soda\Rules\Structure\MaxMethodLength;
use Cosmira\Soda\Rules\Structure\MethodsFollowCallOrder;

return Soda::configure()
    ->withPaths([
        'src/',
    ])
    ->with([
        new MaxFileLoc(700),
        new MaxLineLength(100),
        new MaxMethodLength(100),
        new MethodsFollowCallOrder(),
        new NoAssignmentInCondition(),
        // ... more generated built-in rules
    ]);
```

## Paths

Use `withPaths()` for the default paths analysed by `vendor/bin/soda quality`:

```php
use Cosmira\Soda\Config\Soda;

return Soda::configure()
    ->withPaths([
        'app/',
        'src/',
    ])
    ->with([
        // rules
    ]);
```

CLI paths still work and override the need for configured paths:

```bash
vendor/bin/soda quality src tests
```

## Rules

Rules are plain objects. Thresholds and options live in constructors:

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Complexity\MaxCyclomaticComplexity;
use Cosmira\Soda\Rules\Naming\BooleanMethodPrefix;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new MaxCyclomaticComplexity(10),
        new BooleanMethodPrefix(
            ignore: ['runningUnitTests'],
            prefix: ['is', 'has', 'can'],
        ),
    ]);
```

Remove a rule from `with([...])` to disable it.

Rules without thresholds are configured as objects too:

```php
new NoAssignmentInCondition()
```

Line length is configured the same way:

```php
new MaxLineLength(100)
```

PHPDoc visibility is configured with a list. Method, property, and constant
PHPDoc rules are opt-in so clear, fully typed application code does not require
ceremonial comments.

Libraries and teams with an explicit documentation policy can
add those rules manually. Every violation in the selected visibility scope is reported:

```php
new MultilineMethodPhpDoc(['public'])
new MultilinePropertyPhpDoc(['public'])
new MultilineConstantPhpDoc(['public'])
```

Enabling these rules reports every violation; there is no project-wide allowance
for a number of failures. `MethodsFollowCallOrder()` also takes no allowance.
When migrating an older configuration, remove its numeric argument and the
second argument of the PHPDoc rules. Thresholds such as `MaxArguments(5)` still
define what constitutes a violation; they do not hide existing violations.

Soda currently has no docblock suppression directive. Any future exception
mechanism must be explicit at the affected declaration in a docblock, rather
than hiding an arbitrary number of findings across the project.

## Custom Rules

Custom rules extend `Cosmira\Soda\Rules\Check` and can be placed directly in `with([...])`:

```php
use Cosmira\Soda\Config\Soda;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new App\Soda\Rules\NoRepositoryFromController(),
    ]);
```

There is no separate JSON config and no secondary `config/soda.php`. Keep the root `soda.php` as the single source of truth.
