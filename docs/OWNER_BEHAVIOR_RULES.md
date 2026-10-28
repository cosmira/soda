# Owner behavior policies

These six standard checks identify structural evidence for keeping behavior on
its owner, organizing it in concerns (traits), and expressing repeated setup as a
fluent scenario. They do not require a trait quota, a class split, or chaining every
method. A finding is a review signal, not proof that two operations mean the same
thing. There are no automatic fixes.

Use this overview to distinguish related concerns before selecting a rule.
Individual rule pages are the primary reference for detection boundaries and
constructor options. Read a finding as a reason to inspect ownership, rather
than an instruction to extract a class or add a fluent API.

## Standard configuration

```php
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\Soda;

return Soda::configure()->withPaths(['app/'])->with(RuleCatalog::standard());
```

All are discoverable through `list:rules`, with warning severity. They are included
in `RuleCatalog::standard()` and generated `init` configurations.
Their violations still fail the quality gate. Values report participating classes,
owners, methods, or consumers; thresholds show the maximum count below detection.

## FragmentedOwnerBehavior

**Smell:** several small stateless classes implement actual behavior over the same
owner. Each file reads well, but understanding one owner's operations requires
navigation across the classes.

See [fragmented_owner_behavior](rules/fragmented_owner_behavior.md) for examples, options, and scope.

## RepeatedOwnerConcern

**Smell:** a substantive stateful method group is copied across independent owners.

See [repeated_owner_concern](rules/repeated_owner_concern.md) for examples, options, and scope.

## ScatteredOwnerConcern

**Smell:** related stateful operations are interleaved with unrelated methods in
one owner, forcing the reader to reconstruct a behavior group.

See [scattered_owner_concern](rules/scattered_owner_concern.md) for examples, options, and scope.

## RepeatedConfigurationSequence

**Smell:** repeated fresh-object setup obscures a scenario behind mechanical calls.

See [repeated_configuration_sequence](rules/repeated_configuration_sequence.md) for examples, options, and scope.

## RepeatedForeignStateDecision

**Smell:** multiple consumers reconstruct the same question about a foreign owner's
state, distributing a potential domain rule.

See [repeated_foreign_state_decision](rules/repeated_foreign_state_decision.md) for examples, options, and scope.

## RepeatedForeignStateUpdate

**Smell:** consumers repeatedly compute a change to another object's state.

See [repeated_foreign_state_update](rules/repeated_foreign_state_update.md) for examples, options, and scope.

## Boundaries and verification

Types with inheritance, implemented contracts, existing traits, property hooks,
static state, or behavior magic methods are excluded from owner recommendations.
Duplicate local type declarations are ambiguous and excluded. Unknown external
owners and arbitrary polymorphic calls are not interpreted as proof. Collection
runs only when the `ownerDesign` analysis is requested and reuses the resolved
AST. Project storage retains scalar/array evidence without source or AST objects.

[OwnerDesignRulesTest](../tests/unit/OwnerDesignRulesTest.php) verifies cross-file
findings, boundaries, thresholds, locations, JSON evidence, standard registration,
existing concerns/fluent APIs, and uncertainty. [owner-opportunities.php](../tests/fixtures/design/owner-opportunities.php) provides
one complete scenario that produces all five diagnostics through the normal runner.
[owner-refactored.php](../tests/fixtures/design/owner-refactored.php) shows the
corresponding concerns, owner operations, and fluent report. A runtime test verifies
the illustrated state transitions and report results before/after; all five design
checks accept the refactored scenario. This does not establish safety of arbitrary
refactorings or acceptance by every other Soda rule.

[RepeatedForeignStateUpdateTest](../tests/unit/RepeatedForeignStateUpdateTest.php)
verifies the sixth rule's cross-file expressions, receiver identity, numeric
contracts, overrides, thresholds, JSON evidence, standard registration, and
counterexamples. [state-update-before.php](../tests/fixtures/design/state-update-before.php)
produces one update diagnostic; [state-update-after.php](../tests/fixtures/design/state-update-after.php)
uses an owner operation. The test runs both scenarios and checks identical balances
after each operation, and that the refactored scenario has no update finding.

These tests establish detector
behavior, not real-project precision. No reviewed application corpus or accuracy
percentage is claimed. The rules run by default; calibrate thresholds and review findings in the application context.
