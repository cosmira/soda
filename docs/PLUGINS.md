# Custom checks and rule sets

Configure named classes; the configuration file needs no expression syntax:

```php
return \Cosmira\Soda\Config\Soda::configure()
    ->withPaths(['src/'])
    ->with([new \Cosmira\Soda\Rules\Complexity\MaxCyclomaticComplexity(10)]);
```

Every rule extends `Cosmira\Soda\Rules\Check`. Implement `id()` and the stages
you need: `checkFile(FileFacts)` or `checkProject(ProjectFacts)`. Both stages
otherwise return an empty iterable. `requiredAnalyses()` returns `null` by
default, requesting all collectors; `[]` requests only the shared PHP syntax
and structural facts. It is safe to omit this optimization in a custom rule.

A PHP rule can inspect the ready AST without reopening or parsing the file:

```php
namespace App\Soda\Rules;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;

final class NoVarDump extends Check
{
    public function id(): string { return 'no_var_dump'; }

    public function requiredAnalyses(): ?array { return []; }

    public function checkFile(FileFacts $file): iterable
    {
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, FuncCall::class) as $call) {
            if ($call->name instanceof Name && $call->name->toLowerString() === 'var_dump') {
                yield new Violation(
                    rule: $this->id(), file: $file->path, value: 1, threshold: 0,
                    line: $call->getStartLine(), message: 'Remove the debugging call.',
                );
            }
        }
    }
}
```

Register `new NoVarDump()` in `with([...])`. A project rule receives scalar
metrics in `$project->files`, keyed by path, after known inheritance and trait
composition are resolved. Project facts contain no source or AST. Treat the
shared file AST as read-only and do not retain it between calls.

Rule sets are ordinary arrays of `Check` instances. No plugin class or interface
is needed. Use the catalog for the standard set or a selected section:

```php
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\Soda;

return Soda::configure()
    ->withPaths(['src/'])
    ->with(RuleCatalog::standard());
```

`RuleCatalog::standard()` preserves the standard readonly-data and boolean-prefix
policies. `RuleCatalog::checks('complexity')` selects a section using constructor
defaults, as the former section plugins did. Section names match `list:rules`.
For a reusable custom set, return an array from a PHP file and pass it directly
to `with(require __DIR__.'/team-rules.php')`. Multiple `with()` calls append rules
in order and keep the exact instances supplied; they do not replace rules by ID.

Standard rules come from `resources/catalog.php`; PHPDoc requirements remain
opt-in. Built-in constructor options and metric thresholds remain on the rule
instances. Numeric violation allowances are not supported.

For expression-based checks, see [Rule expressions](RULE_EXPRESSIONS.md).
