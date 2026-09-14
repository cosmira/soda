# Naming Rules

Naming rules keep identifiers short enough to scan and specific enough to
understand. They also catch names that repeat information already present in
types, return values, or class context.

**Config:** Naming rules are plain objects in `soda.php`.

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Naming\AvoidRedundantNaming;
use Cosmira\Soda\Rules\Naming\BooleanMethodPrefix;
use Cosmira\Soda\Rules\Naming\ClassNameLength;
use Cosmira\Soda\Rules\Naming\MethodNameLength;
use Cosmira\Soda\Rules\Naming\MaxVariableNameWords;
use Cosmira\Soda\Rules\Naming\NamespaceNameLength;
use Cosmira\Soda\Rules\Naming\VariableNameLength;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new AvoidRedundantNaming(80),
        new BooleanMethodPrefix(),
        new VariableNameLength(min: 3, max: 16),
        new MaxVariableNameWords(maxWords: 1, ignore: ['openAI']),
        new MethodNameLength(min: 3, max: 32),
        new ClassNameLength(min: 3, max: 32),
        new NamespaceNameLength(min: 3, max: 32),
    ]);
```

| Rule | Purpose | Default |
|------|---------|---------|
| `avoid_redundant_naming` | Reports names that repeat type or class context. Similarity threshold: 0 disables, 1-100 is percent. Minimum word length: 4. | 80 |
| `boolean_methods_without_prefix` | Reports boolean-returning methods that do not start with a question-style prefix. | allowed prefixes: is/has/can/should/... |
| `variable_name_length` | Minimum and maximum variable name length. | min 3 / max 16 |
| `max_variable_name_words` | Optional policy for local variables and parameters that combine multiple concepts. | opt-in |
| `method_name_length` | Minimum and maximum method name length. | min 3 / max 32 |
| `class_name_length` | Minimum and maximum class, interface, trait, and enum name length. | min 3 / max 32 |
| `namespace_name_length` | Minimum and maximum namespace segment length. | min 3 / max 32 |

---

## avoid_redundant_naming

Reports names that can usually be simplified because part of the name is
already conveyed by a parameter type, return type, or surrounding class name.

### How It Works

1. Split camelCase words:
   - `PostItemCollection` → `["Post", "Item", "Collection"]`
   - `addPostItem` → `["add", "Post", "Item"]`
   - `getAllUsers` → `["get", "All", "Users"]`

2. Compare words with the configured similarity threshold.

3. For class names, remove repeated filler before common suffixes such as
   `Collection`, `Repository`, or `Service`.

4. For `add`, `has`, and `remove` methods, compare the method suffix with the
   first parameter type.

5. For `add`, `has`, and `remove` methods, compare the method suffix with the
   surrounding class context.

6. For `get`, `find`, and `list` methods returning collections, prefer shorter
   collection-oriented names.

### Ignored

- Magic methods such as `__construct` and `__toString`.
- Classes ending in `Exception`, `Interface`, `Trait`, `Abstract`, or `Test`.
- Parameters typed as `*Interface`, such as `addLogger(LoggerInterface $logger)`.

### Examples

```
Redundant naming detected
  Current: addPostItem(PostItem $...)
  Suggested: add(PostItem $...)
  Reason: "PostItem" already conveyed by parameter type
  Similarity score: 92%
```

```
PostItemCollection → PostCollection
addUserProfileData(UserProfileData $d) → add(UserProfileData $d)
addUser() in UserService → add()
addPost(Post $post) in PostCollection → add(Post $post)
hasPost(Post $post) in PostCollection → has(Post $post)
clearPost() in PostCollection → clear()
getAllOrders(): Order[] → all() or getAll()
addLogger(LoggerInterface $logger) stays unchanged
```

Contextual shortening is not limited to `add` and `has`. When the suffix of an
action repeats the entity already established by a role class such as
`PostCollection`, `UserService`, or `OrderRepository`, Soda suggests the action
alone. Additional meaning is preserved: `clearPostCache()` is not reduced to
`clear()`. Methods marked `#[\Override]` are also left untouched because their
names belong to an inherited contract.

## boolean_methods_without_prefix

Reports boolean-returning methods whose names do not read like questions.
Allowed prefixes include `is`, `has`, `can`, `should`, `does`, `was`, `are`,
`do`, `did`, `will`, and `try`. Command-style names such as `run`, `handle`,
and `save` are ignored by default.

```php
new BooleanMethodPrefix()
```

```php
// Good
public function isActive(): bool
{
    return $this->active;
}
```

```php
// Bad
public function active(): bool
{
    return $this->active;
}
```

## Name Length Rules

Use the constructor to tune the minimum and maximum lengths:

```php
new VariableNameLength(min: 3, max: 16)
new MethodNameLength(min: 3, max: 32)
new ClassNameLength(min: 3, max: 32)
```

Soda ignores `$this` and PHP superglobals for variable name length checks.
The framework-prescribed `up()` and `down()` hooks are also exempt from the
minimum method-name length when their class directly extends `Migration` and
declares the complete public zero-argument migration contract. The base class
must resolve to `Illuminate\Database\Migrations\Migration`; an application
class merely named `Migration` receives no exception. Other short
methods on the same class remain checked.

## max_variable_name_words

This rule is intentionally opt-in. Standard SODA lets local context determine
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
        $celsius = self::convertFahrenheitToCelsius($degrees);

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
