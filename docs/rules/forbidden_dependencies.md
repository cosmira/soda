# forbidden_dependencies

📝 Enforce directed namespace boundaries: namespaces declared as source may not depend on the target namespaces you forbid.

💼🚫 This rule is **not** enabled by default. Add it to your `soda.php` config to enable it.

## Why

Layering conventions such as "domain must not depend on HTTP" or "analysis must not depend on commands" only help when they are checked. Without an automated boundary, a single `use` statement quietly couples two modules and the layering erodes one exception at a time. The rule reports each prohibited source-to-target dependency edge once, at the owning class or trait declaration, and advises moving the dependency across the configured module boundary.

Prefixes may name a namespace or an exact class, including global classes such as `CActiveRecord`. Matching is case-insensitive and happens at namespace boundaries: `App\Analysis` matches `App\Analysis\Nested` but not `App\AnalysisExtra`.

## Examples

```php
// ❌ App\Analysis may not depend on App\Commands
namespace App\Analysis;

use App\Commands\Run as Command;

class Worker
{
    public function run(Command $input): Command
    {
        return new Command;
    }
}

// ✅ The reverse direction is allowed, and so is an unrelated namespace
namespace App\Commands;

use App\Analysis\Report;

class Handler
{
    public function handle(Report $report): void {}
}
```

Unused `use` imports are ignored: only resolved type dependencies (parameter types, return types, `new`, static calls, `extends`, `use` traits) count.

The ❌ case is reported only with a configured mapping such as the one below; with an empty or missing `$forbidden` mapping the rule imposes no boundaries.

## Options

| Argument | Type | Default | Description |
| --- | --- | --- | --- |
| `$forbidden` | `array<string, list<string>>` | `[]` | Maps a source namespace prefix to the list of target prefixes it must not depend on. An empty mapping imposes no boundaries. Empty prefixes are rejected with an `InvalidArgumentException`. |

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Architecture\ForbiddenDependencies;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new ForbiddenDependencies([
            'App\Analysis' => ['App\Commands'],
        ]),
    ]);
```

## Related rules

- [no_dependency_cycles](no_dependency_cycles.md) — detects mutual dependencies between modules in both directions.
- [max_dependencies](max_dependencies.md) — caps how many collaborators a single class may depend on.
