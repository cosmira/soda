# Expressions inside named rules

Consumers select PHP classes and constructor arguments. They do not need to
learn a DSL, and `soda.php` does not accept a universal `Config\Rule(id, expression)`.
Authors may extend `Rules\ExpressionCheck` to implement numeric or compound
conditions with phplrt. More involved rules implement `Rules\Check` in PHP.

```php
namespace App\Soda\Rules;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\ExpressionCheck;
use InvalidArgumentException;

final class MaxServiceMethods extends ExpressionCheck
{
    public function __construct(private readonly int $maximum = 15)
    {
        if ($maximum < 1) {
            throw new InvalidArgumentException('maximum must be positive.');
        }
    }

    public function id(): string { return 'max_service_methods'; }

    protected function expression(): string
    {
        return sprintf('class where name matches "*Service" and methods > %d', $this->maximum);
    }

    protected function violation(FileFacts $file, array $row): Violation
    {
        return new Violation(
            rule: $this->id(), file: $file->path,
            value: $row['methods'], threshold: $this->maximum,
            class: $row['name'], line: $row['line'] ?? null,
        );
    }
}
```

Use `new MaxServiceMethods(10)` in the existing fluent configuration. Validate
constructor values before interpolating them into expressions. The predicate is
compiled once per rule instance. It selects rows; the rule creates violations
with the measured value, configured threshold, message and source position.
The predicate's boolean result is not the reported metric.

The existing scopes remain `file`, `class` and `method`. File and method
expressions run in the file stage. Class expressions run after project
aggregation so public methods, properties and interfaces include known inherited
and trait-composed surfaces. No additional DSL scopes or operators are introduced.

Numbers support `==`, `!=`, `<`, `<=`, `>` and `>=`; strings support equality and
`matches`; `depends_on matches` tests static type names against a glob. Combine
conditions using the existing `and`, `or`, `not` and parentheses syntax.

Discover fields, units, semantics and their calculation sources with:

```bash
php soda list:metrics
php soda list:metrics method
```

`resources/facts.php` is the compiler's schema and the command's source of
truth. It also identifies required analyses. Unknown scopes, fields and invalid
operand types produce configuration errors. Source method names are navigation
metadata; Soda does not dispatch collectors by these strings.

PHP syntax is parsed by `nikic/php-parser`. phplrt parses the expressions over
collected facts. Replacing the PHP frontend is not required for this separation;
other source languages would still need their own syntax and semantic collectors.

The grammar lives in `resources/rules.pp3`; regenerate `resources/rules.php`
with `composer rules:compile`. The generated parser is shipped for runtime use.
The compiler and its lexer/parser builders are pinned so regeneration is
reproducible. Upgrade them together with the generated parser and expression tests.
