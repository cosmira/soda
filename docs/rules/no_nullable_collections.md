# no_nullable_collections

Reports a nullable collection return type when the callable body proves that its
normal returns produce collections. Enabled in the standard configuration.

## Why

`null` and an empty collection can mean different things. Keep nullable results
when they describe a real outcome; remove nullability when the implementation
cannot produce that outcome.

## Examples

```php
// Violating code
function visibilities(): ?array
{
    return ['public', 'protected', 'private'];
}

// Passing code
function visibilities(): array
{
    return ['public', 'protected', 'private'];
}
```

Real nullable outcomes remain accepted:

```php
function parseItems(string $input): ?array
{
    if ($input === 'invalid') {
        return null;
    }

    return [];
}
```

Laravel collections are supported, including imported aliases:

```php
use Illuminate\Support\Collection;

// Violating code: the constructor produces a Collection, never null.
function items(): ?Collection
{
    return new Collection();
}

// Passing code
function items(): Collection
{
    return new Collection();
}
```

## Proof boundaries

- Recognizes `?array`, `array|null`, `?iterable`, `iterable|null`, and nullable known
  Laravel `Collection`, Eloquent `Collection`, `LazyCollection`, and `Enumerable`.
- Accepts array literals, array casts, known collection/iterator constructors, and
  ternary or match expressions whose alternatives all produce the declared kind.
- Checks each callable independently. Nested callable returns are not outer returns.
- Requires a terminating body; implicit fallthrough is not treated as a collection.
- Skips generators, goto, try/catch/finally, references, and unknown return expressions.
- Checks free functions, closures, arrows, private or final methods, and methods in
  final classes. Open extension points and trait methods are accepted.
- Does not infer arbitrary calls, variables, `collect()`, collection factories,
  custom collection subclasses, or PHPDoc-only return types. Project code is never
  loaded to discover an iterable implementation.

There are no constructor options or automatic rewrites. Additional union members
are preserved, even if one of them is a collection.

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Usage\NoNullableCollections;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([new NoNullableCollections()]);
```

## Related rules

- [no_redundant_array_keys](no_redundant_array_keys.md). Removes repeated list-key syntax.
- [no_untyped_parameters](no_untyped_parameters.md). Requires explicit parameter contracts.
