# Practical design

Soda should make an everyday change easier to understand and implement. A lower count of methods, statements or dependencies is useful only if the resulting code is easier to follow. Moving each step into a new object can improve local metrics while making the scenario harder to read.

Start with the calling code. Keep a small operation beside the state it uses. Extract an object when it has a concrete purpose: an invariant, a meaningful boundary, its own state or lifetime, or an independently useful operation. A class that forwards everything unchanged can add navigation without adding a concept.

Traits are a legitimate way to organize a cohesive concern of the owning object. Reuse is useful but not mandatory: one owner may benefit from a named slice of behavior. Describe the state and methods a trait expects, test it through its owner, and resolve conflicts explicitly. Moving methods into traits solely to satisfy a count is not a design improvement.

Fluent APIs work well when their call sequence describes a task. A mutable builder should make the construction/execution boundary apparent; an immutable value chain should preserve the original. Neither chaining nor copying is intrinsically a design defect. Keep normal methods when a fluent interface would obscure ordering, effects or return values.

Value objects express a concept and its operations or invariants. Related amounts and units, or a date interval, can deserve a value object. A one-off array, transport payload, simple readonly record or primitive does not automatically deserve a wrapper, factory and service. Prefer an object when it removes repeated interpretation or keeps a rule about the data in one place.

Use functions and private helpers for small local work. Preserve adapters that convert data or implement a required external contract, decorators with behavior, framework contracts and named public rule classes. An interface added only to silence a detector is not evidence that a boundary became useful.

[DHH's account of concerns](https://signalvnoise.com/posts/3372-put-chubby-models-on-a-diet-with-concerns) argues for organizing model behavior without proliferating separate objects, including concerns with one owner. His [Pattern vision](https://signalvnoise.com/posts/3341-pattern-vision) cautions against speculative pattern application. Laravel provides concrete examples through [query builders](https://laravel.com/docs/13.x/queries), [Eloquent concerns](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Database/Eloquent/Model.php) and [value-object casting](https://laravel.com/docs/13.x/eloquent-mutators#value-object-casting). These sources inform Soda's choices; they are not endorsements of Soda or a universal formula for their authors' taste.

## Executable examples

[practical.php](../tests/fixtures/design/practical.php) demonstrates a concern owned by an invitation, a mutable report draft, an explicit immutable-style query copy, readonly settings, money arithmetic, a function and direct storage use. [PracticalDesignTest](../tests/unit/PracticalDesignTest.php) verifies their runtime behavior and the specific rule contracts under review. It does not claim that every historical strict rule accepts all these examples. The multi-class fixture is a compact test module, not a recommendation to ignore a project's file-organization conventions.

[NoTrivialDelegatingClassesTest](../tests/unit/NoTrivialDelegatingClassesTest.php) supplies the contrasting forwarding classes. Multiple transparent operations should not make a wrapper more valuable; changing the operation or adding domain behavior is a materially different case.

## Evidence before enforcement

Distinguish an AST fact, a correct metric and a useful recommendation. Test equivalent syntax, legitimate counterexamples and the actual transformation suggested to users. A metric must state what it counts and what it cannot know. Unknown calls are not proof of absent relationships. Cohesion is not a count of business responsibilities; a common logger can connect unrelated operations, while a useful concern can group methods without shared state.

Heuristic rules enter as opt-in checks until PHP examples and corpus review justify broader use. This is rule selection, not a budget of tolerated violations: enabled checks still require zero violations. Do not reward a quota of traits, value objects, interfaces or files. Compare the code and the calling scenario before and after the proposed change.

See [Policy Compatibility](POLICY_COMPATIBILITY.md) for the deliberate decisions on clone, guards, directory names and array access.
