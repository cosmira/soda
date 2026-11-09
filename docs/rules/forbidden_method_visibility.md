# forbidden_method_visibility

📝 Forbid selected method visibilities in specific files or classes.

💼🚫 This rule is not enabled by default. Add it to your `soda.php` config to enable it.

## Why

A controller can have a public method for each action and leave supporting behavior to the objects it calls. Private and protected helpers can make that boundary harder to maintain: the controller starts owning behavior that other parts of the application also need.

`ForbiddenMethodVisibility` lets you enforce that convention where it applies. By default, it reports private and protected methods in the files or classes you select. You may forbid any combination of `public`, `protected`, and `private` methods.

Choose the classes that need the restriction, and keep internal methods private elsewhere. Making a helper public just to silence the rule exposes the same behavior through a larger API.

## Examples

With `paths: ['app/Http/Controllers']`, the following controller produces a finding for `orderData()`:

```php
use App\Models\Order;

// ❌ A private helper in a controller selected by the rule.
final class OrderController
{
    public function show(Order $order): array
    {
        return $this->orderData($order);
    }

    private function orderData(Order $order): array
    {
        return [
            'number' => $order->number,
            'total' => $order->total,
        ];
    }
}
```

This helper has one caller and only builds a small array. You can remove the helper and keep the same response:

```php
use App\Models\Order;

// ✅ The action returns the same data without a private helper.
final class OrderController
{
    public function show(Order $order): array
    {
        return [
            'number' => $order->number,
            'total' => $order->total,
        ];
    }
}
```

For behavior shared by several actions, move the behavior to the object that owns it or to a collaborator. The appropriate change depends on what the method does.

## Options

| Argument | Type | Default | Description |
| --- | --- | --- | --- |
| `$paths` | `list<non-empty-string>` | `[]` | File paths, directory paths, or glob patterns such as `*Controller.php`. |
| `$classes` | `list<non-empty-string>` | `[]` | Fully qualified class names or glob patterns such as `App\Http\Controllers\*`. |
| `$visibilities` | `list<'public'\|'protected'\|'private'>` | `['private', 'protected']` | Method visibilities to forbid. An empty list produces no findings. |

If both `$paths` and `$classes` are empty, the rule checks every class in the analyzed files. If either list contains a selection, the rule checks matching classes only. When you supply both lists, a match in either list is enough. Overlapping selections produce one finding per method.

Empty or blank entries in `$paths` or `$classes`, and unsupported values in `$visibilities`, throw an `InvalidArgumentException`.

## Configuration

### Select files

To require public methods in your controllers, add the rule to `soda.php`:

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Structure\ForbiddenMethodVisibility;

return Soda::configure()
    ->withPaths(['app/'])
    ->with([
        new ForbiddenMethodVisibility(paths: ['app/Http/Controllers']),
    ]);
```

`withPaths()` determines which files soda analyzes. The rule's `paths` argument selects files within that set. It does not add files to the analysis.

A directory selection includes its descendants. For example, `app/Http/Controllers` includes `app/Http/Controllers/Admin`, but does not include `app/Http/ControllersLegacy`. A filename pattern such as `*Controller.php` matches that filename at any directory depth.

### Select classes

Use `classes` when the restriction follows class names rather than file locations:

```php
new ForbiddenMethodVisibility(classes: [App\Http\Controllers\OrderController::class]);

new ForbiddenMethodVisibility(classes: ['App\\Http\\Controllers\\*']);
```

Class names are case-insensitive and may start with `\`. Use the full namespace for namespaced classes, or a bare name for a class in the global namespace. In a class pattern, `*` can span namespace separators. Selecting a class by name does not select its subclasses.

### Choose the forbidden visibilities

To forbid private methods while allowing protected methods, pass `['private']`:

```php
new ForbiddenMethodVisibility(
    paths: ['app/Http/Controllers'],
    visibilities: ['private'],
);
```

You may also forbid public methods, including methods declared without an explicit visibility modifier:

```php
new ForbiddenMethodVisibility(
    classes: ['App\\Internal\\*'],
    visibilities: ['public'],
);
```

### Exclude files

To exempt files from one configured rule instance, chain `exclude()`:

```php
(new ForbiddenMethodVisibility(paths: ['app/Http/Controllers']))
    ->exclude(['app/Http/Controllers/Legacy']);
```

The excluded files remain available to other rules. See [per-rule exclusions](../SODA_PHP_CONFIG.md#per-rule-exclusions).

### Understand which methods are checked

The rule reports each matching method at its declaration. It includes constructors, static methods, and abstract method declarations. A method without an explicit visibility modifier counts as public.

The rule checks methods declared directly in a class. It does not expand inherited methods, methods from traits, or trait visibility adaptations. Trait, interface, and enum declarations are outside its scope.

A file selected through `paths` includes anonymous classes in that file. Selecting an outer class through `classes` does not select a nested class.

## Related rules

- [max_public_methods](max_public_methods.md) limits the number of public methods a class exposes.
- [no_trivial_delegating_classes](no_trivial_delegating_classes.md) reports classes whose methods only forward calls to another object.
