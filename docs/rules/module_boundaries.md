# module_boundaries

Keep module internals private. Sibling modules communicate through exported namespaces.

This rule is **opt-in**. Its root namespace is required. Modules are the immediate
children of that root. `Events` and `Facades` are public; everything else belongs
to its module.
New modules follow the same policy without another configuration entry.

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Architecture\ModuleBoundaries;

new ModuleBoundaries('App\\Modules');

// Temporary access to all types in Access; Access must still respect other modules.
new ModuleBoundaries('App\\Modules', except: ['Access']);

// A different module layout and public API convention.
new ModuleBoundaries(namespace: 'Domain', exports: ['Contracts']);
```

| Argument | Default | Meaning |
| --- | --- | --- |
| `namespace` | Required | Namespace whose immediate children are modules. |
| `exports` | `['Events', 'Facades']` | Public namespace prefixes relative to each module. An empty list exports nothing. |
| `except` | `[]` | Module names whose types remain accessible from every sibling module. |

Names match case-insensitively at namespace boundaries. Leading and trailing
backslashes are accepted; empty prefixes throw `InvalidArgumentException`.
Exceptions apply to the **target**, not the caller. They do not exempt a module
from respecting its neighbors.

## Monorepositories

Register one instance per module root. Each instance keeps its own exports and
exceptions; registering another instance does not replace the previous one.
Use PHP namespace separators, even when the directories use forward slashes.

```php
return Soda::configure()
    ->withPaths(['app/'])
    ->with([
        new ModuleBoundaries('App\\ModulesAgent', except: ['Access']),
        new ModuleBoundaries('App\\ModulesClient'),
    ]);
```

These instances protect siblings **within each root**. Dependencies between
`ModulesAgent` and `ModulesClient` are outside both policies. To restrict those
roots as sibling modules too, configure their common parent separately.

## Soda checks itself

Soda's own `soda.php` isolates rule categories:

```php
new ModuleBoundaries('Cosmira\\Soda\\Rules', exports: []),
```

For example, an Architecture rule cannot depend on a Naming rule. Categories
share `Analysis`, `Contracts`, and `Reporting`, which are outside this root.

## Examples

Inside `App\Modules\Audit`:

```php
// Allowed: public API of a sibling module.
\App\Modules\Directory\Events\PersonChanged::class;
\App\Modules\Directory\Facades\Directory::people();

// Allowed: internals of the same module.
\App\Modules\Audit\Models\AuditRecord::query();

// Reported: private type of another module.
\App\Modules\Directory\Models\Person::query();
```

Nested exported namespaces are public too. `EventsExtra` and `Internal\Events`
are private. Dependencies outside the configured root and callers outside that
root are unchanged. A type directly under the root is not a module.

## Analysis boundaries

The rule reuses the resolved type dependencies used by
[forbidden_dependencies](forbidden_dependencies.md): aliases, type declarations,
instantiation, inheritance, traits, and static references. Unused imports are
ignored. Classes, traits, interfaces, and enums are checked. Each private
dependency is reported once per owning declaration, at its declaration line,
with both type names in the message. Referenced types do not have to be present
in the analyzed paths.

This is a direct type-dependency check. It does not trace runtime calls through
exports, strings passed to containers, dynamically constructed class names,
or standalone functions.

## Related rules

- [forbidden_dependencies](forbidden_dependencies.md) restricts dependency direction.
- [no_dependency_cycles](no_dependency_cycles.md) detects cycles, including cycles through public APIs.
