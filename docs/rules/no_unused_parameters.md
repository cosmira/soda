# no_unused_parameters

📝 Reports function and method parameters that are never used.

💼 This rule is enabled in the ✅ `standard` config.

## Why

An unused input is dead weight left by a changed implementation, or an undocumented contract. Readers must reason about a value that never matters, and callers provide arguments that do nothing. The rule reports unused parameters and advises removing them when the signature is owned locally.

Positions required by an implemented interface or parent method are preserved. Callback positions before the last used slot, promoted constructor properties, and explicitly configured external contracts are also preserved. This includes stream and array-callback signatures. A parameter overwritten before its first read counts as unused because the incoming value never reaches a read.

PHP's fixed magic-method positions are also preserved; `__invoke` and ordinary
constructors retain their own signatures. Reads through `func_get_args()`,
`func_get_arg()`, `compact()` and included code count within their owning callable.
An exit in a `try` block, switch case or match arm does not hide reads in
alternative paths or `finally`. Match conditions short-circuit within an arm;
`default` is selected after the explicit conditions even when written first.

Abstract trait methods also require compatible parameter positions, including
private methods and constructors. Trait aliases retain their own abstract
requirements. A concrete implementation in an intermediate trait fulfills that
requirement before later composition; an ordinary concrete trait method does
not fix the signature of a class override. Additional positions remain eligible
for diagnostics.

Anonymous classes retain declared parent and interface contracts. Their identities
are scoped to the source file, and additional parameters beyond the inherited
signature remain eligible for diagnostics.

Inherited signatures from loaded PHP internal classes and interfaces (such as
`ArrayAccess`) are also preserved. Only internal metadata is inspected; application
autoloaders are never invoked and loaded application classes do not supply contracts.

When an ancestor or imported trait cannot be resolved from available source,
public and protected methods may still implement an unknown signature. These
positions are preserved rather than treating missing dependency information as a
violation. Private methods retain their owned inputs;
complete known contracts still preserve only their declared positions.

Observed positional calls can also establish a protocol for a factory's declared
receiver family. The rule follows a top-level `$this` factory binding with a native
or single PHPDoc return class, open incoming arguments, and computed construction.
It preserves only observed public positions on real descendants and selected trait
methods. This records possible signature ownership, not proof that every descendant
is constructed or that its body reads the argument.

Concrete factories, finite or aliased class selections, receiver reassignment,
reference escapes, and writes through local symbol tables do not establish this
family protocol. Nested callable scopes stay separate. Named and unpacked calls,
first-class callable references, union return types, and namespace-prefix formatter
chains alone are not used to infer positional contracts. Native `method_exists`
guards are accepted only when their namespace fallback is not shadowed by an
analyzed function declaration. Factory declarations are currently read on the
same class; arbitrary collaborator factories are not inferred.

A computed call on `$this` can establish the same positional protocol for selected
public or protected methods. Native concatenation and interpolation must contain
literal text and an open segment. The name can be computed directly at the call,
selected by an earlier assignment in the same statement list, or selected in the
successful body of an unshadowed native `method_exists` guard. Literal bounds do
not require assumptions about embedded formatter APIs. Selected caller and callee trait
origins are respected. Closed literal or aliased selector inputs, overwritten names,
foreign receivers, and nested callable bindings do not donate these positions.
Nonlocal jumps invalidate binding protocols. Other computed expression operators,
such as casts and conditional selections, are left unclassified.

A computed call through a native instance field can also establish a public
receiver protocol. The selected public constructor must store its original typed
input, or read a pure zero-argument getter on an original typed dependency. That
getter must return the same storage slot written by a selected public setter from
its original native input. The setter's actual input family bounds the positions;
a broader field type does not extend the protocol to unrelated siblings.

Private field ownership follows the selected method's lexical scope, including
inherited methods and traits. Additional writes, unknown reference escapes,
intercepted/static/redeclared storage, finite factories and constructor effects
outside plain input wiring are not used to infer this protocol. Nested callback
receivers remain separate; a lexical arrow can be rebound before invocation.

A successful native `method_exists($receiver, $selector)` on the exact collaborator
also proves that an OR fallback is skipped for its declared target methods. This
requires the invocation in that `if` body's successful branch, an unchanged selector
and receiver, and an unshadowed native guard. Other OR operands, earlier escapes,
and unrelated guards do not establish this path. Readonly backing fields accept
open constructor injection; later public setters cannot replace initialized storage.
Ordinary native backing fields still retain their compatible input families.

## Examples

```php
// ❌
function operation($input, $mode): int
{
    return $mode === 'count' ? 1 : 0; // $input is never read
}

// ✅
function operation($input, $mode): int
{
    return $mode === 'count' ? strlen($input) : 0;
}
```

```php
// ❌
function handle(Request $request, string $guard): Response
{
    return new Response($request->all()); // $guard is never read
}

// ✅
function handle(Request $request): Response
{
    return new Response($request->all());
}
```

```php
// ✅ Preserved: the position is required by the interface contract
interface Contract
{
    public function run($input);
}

class Worker implements Contract
{
    public function run($input) {}
}
```

## Options

| Argument | Type | Default | Description |
| --- | --- | --- | --- |
| `contracts` | `list<string>` | `[]` | Exact qualified method or function names (e.g. `App\Worker::run`) whose signatures are fixed by external consumers. Unused parameters on these callables are never reported. |

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Usage\NoUnusedParameters;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new NoUnusedParameters(contracts: [
            'App\Contracts\Handler::handle',
        ]),
    ]);
```

## Related rules

- [unused_methods](unused_methods.md) — the method-level counterpart: private and protected methods that are never called.
- [no_reassigned_parameters](no_reassigned_parameters.md) — flags inputs that are read but rebound instead of being kept stable.

A separate finite protocol recognizes a lexical by-value `foreach` over an inline string list or an unmodified non-public instance property default. The loop must be a direct method statement after a supported continuing prefix. It must contain exactly one positional `$this->{$task}(...)` invocation. The selected caller, property slot, and every listed method must resolve to source declarations.

Only positions before the target's last incoming-value read are preserved, bounded by the supplied arguments. Every position in that prefix must also be read by some selected task. Trailing unused parameters and ordinary fixed-method calls remain eligible. A parent's private target stays bound to its lexical scope when a child reuses its name.

The following conditions do not establish this protocol:

- Sparse read sets, keyed lists, keys as selectors, or references.
- Duplicated or locally aliased argument variables, or receiver escapes.
- Mutable or redeclared lists, ambiguous declarations, or unavailable bodies.
- Eager terminal operands or indirect invocations in the prefix.
- Variable-table receiver exposure, unsupported preceding control flow, or nested callable receivers.
