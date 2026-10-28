# Structure and dependencies

Use these topics to choose which structural policies fit your project. For a
specific diagnostic, open its individual rule page; that page is the primary
reference for behavior, constructor options, and analysis boundaries.

- [Size of classes and methods](#size-of-classes-and-methods)
- [Structural smells](#structural-smells)
- [Method reading order](#method-reading-order)
- [Control flow clarity](#control-flow-clarity)
- [PHPDoc contracts](#phpdoc-contracts)
- [Structure and coupling](#structure-and-coupling)
- [Owner behavior](#owner-behavior-recommendations-standard)

Choose limits that reveal a concrete reading or maintenance problem. A lower
number alone does not justify changing an API, extracting a class, or discarding
behavior. Compare related checks before adding multiple limits for the same
concern. See [Configuration](SODA_PHP_CONFIG.md) for rule selection.

These are documentation topics, not API section names. The CLI and
`RuleCatalog::checks()` use `structural`, `complexity`, and `naming`. Enabling a
rule and deciding whether it can fail the check are separate properties; the
[rule index](rules/README.md) records both.

## Size of classes and methods

### max_method_length

Limits the source length of a single method. Read [examples, options, and analysis boundaries](rules/max_method_length.md).

### max_class_length

Limits the source length of a single class declaration. Read [examples, options, and analysis boundaries](rules/max_class_length.md).

### max_file_loc

Limits the source length of a single file. Read [examples, options, and analysis boundaries](rules/max_file_loc.md).

### max_line_length

Limits the length of physical source lines. Read [examples, options, and analysis boundaries](rules/max_line_length.md).

## Structural smells

### max_todo_fixme_comments

Reports TODO and FIXME comments left in source code. Read [examples, options, and analysis boundaries](rules/max_todo_fixme_comments.md).

### max_commented_out_code_lines

Reports source code that has been disabled by turning it into comments. Read [examples, options, and analysis boundaries](rules/max_commented_out_code_lines.md).

### no_vacuous_docblocks

Flags PHPDoc descriptions that add nothing beyond the member's own name. Read [examples, options, and analysis boundaries](rules/no_vacuous_docblocks.md).

### max_empty_catch_blocks

Reports catch blocks that silently discard the caught exception. Read [examples, options, and analysis boundaries](rules/max_empty_catch_blocks.md).

### max_ask_then_tell_patterns

Reports code that queries an object's state and then commands the same object based on the answer. Read [examples, options, and analysis boundaries](rules/max_ask_then_tell_patterns.md).

### no_trivial_delegating_classes

Reports behavior-free classes that only pass their operations through to one dependency. Read [examples, options, and analysis boundaries](rules/no_trivial_delegating_classes.md).

### max_layer_dominance_percentage

Reports directories where one architectural role dominates beyond a configured percentage without the directory being named for that role. Read [examples, options, and analysis boundaries](rules/max_layer_dominance_percentage.md).

## Method reading order

### methods_follow_call_order

Flags private helpers declared in an order that contradicts the order their sole caller uses them. Read [examples, options, and analysis boundaries](rules/methods_follow_call_order.md).

## Control flow clarity

### no_assignment_in_condition

Forbids assignments inside control-flow conditions. Read [examples, options, and analysis boundaries](rules/no_assignment_in_condition.md).

### only_list_arrays

Discourages structured array usage; only list arrays are allowed. Read [examples, options, and analysis boundaries](rules/only_list_arrays.md).

### no_numeric_array_index

Review repeated positional reads of an explicitly documented array tuple. Read [examples, options, and analysis boundaries](rules/no_numeric_array_index.md).

### unused_methods

Flags private and protected methods that are never called in the project. Read [examples, options, and analysis boundaries](rules/unused_methods.md).

### useless_variable

Detects variables that are direct copies of another variable and adds nothing. Read [examples, options, and analysis boundaries](rules/useless_variable.md).

## PHPDoc contracts

All three rules require a description of the member's purpose or contract before
any block tags. A block containing only `@test`, `@param`, `@return`, `@var`, or
other tags does not qualify, even if a tag has a description on a following line.
Blank lines, punctuation, and numbers alone do not count as a description.
Descriptions may use any language. The check verifies the presence of text;
it cannot judge whether that text accurately explains the member.

```php
// Rejected: tags alone do not explain the method's purpose.
/**
 * @test
 */
public function verifiesDelivery(): void {}

// Accepted: the description precedes the tags.
/**
 * Verify that a paid order is scheduled for delivery.
 *
 * @test
 */
public function verifiesDelivery(): void {}
```

### multiline_method_phpdoc

Requires methods to be documented with a multiline PHPDoc block that contains a written description before any tags. Read [examples, options, and analysis boundaries](rules/multiline_method_phpdoc.md).

### multiline_property_phpdoc

Requires properties — including promoted constructor parameters — to be documented with a multiline PHPDoc block that contains a written description before any tags. Read [examples, options, and analysis boundaries](rules/multiline_property_phpdoc.md).

### multiline_constant_phpdoc

Requires class constants to be documented with a multiline PHPDoc block that contains a written description before any tags. Read [examples, options, and analysis boundaries](rules/multiline_constant_phpdoc.md).

## Number of dependencies

### max_arguments

Limits the number of parameters a method or constructor may accept. Read [examples, options, and analysis boundaries](rules/max_arguments.md).

### max_dependencies

Limits the number of constructor dependencies of a class. Read [examples, options, and analysis boundaries](rules/max_dependencies.md).

### max_efferent_coupling (Ce)

Limits the number of distinct external types a class references. Read [examples, options, and analysis boundaries](rules/max_efferent_coupling.md).

### max_properties_per_class

Limits the number of properties declared in a single class. Read [examples, options, and analysis boundaries](rules/max_properties_per_class.md).

### max_public_methods

Limits the number of public methods a class exposes, including inherited and trait-composed ones. Read [examples, options, and analysis boundaries](rules/max_public_methods.md).

## Structure and coupling

### max_methods_per_class

Limits the number of methods declared in a single class. Read [examples, options, and analysis boundaries](rules/max_methods_per_class.md).

### max_classes_per_file

Limits the number of named classes and traits declared in a single file. Read [examples, options, and analysis boundaries](rules/max_classes_per_file.md).

### max_namespace_depth

Limits the number of segments in the namespace enclosing a class. Read [examples, options, and analysis boundaries](rules/max_namespace_depth.md).

### max_classes_per_namespace

Limits the number of classes and traits that share one namespace. Read [examples, options, and analysis boundaries](rules/max_classes_per_namespace.md).

### max_traits_per_class

Limits the number of traits a single class composes. Read [examples, options, and analysis boundaries](rules/max_traits_per_class.md).

### max_interfaces_per_class

Limits the number of interfaces a single class implements. Read [examples, options, and analysis boundaries](rules/max_interfaces_per_class.md).

### max_classes_per_project

Limits the total number of classes and traits declared across the analyzed project. Read [examples, options, and analysis boundaries](rules/max_classes_per_project.md).

## Method-level structure

### no_else_branches

Reports redundant alternatives after a proven unconditional exit. Read [examples, options, and analysis boundaries](rules/no_else_branches.md).

### no_complex_control_conditions

Requires explicit grouping when a condition mixes logical families. Read [examples, options, and analysis boundaries](rules/no_complex_control_conditions.md).

### no_service_locator_calls

Declare dependencies in the constructor instead of pulling them from the global container with `app()` or `resolve()`. Read [examples, options, and analysis boundaries](rules/no_service_locator_calls.md).

### no_singleton_access

Construct collaborators at the composition root instead of reaching for a static `instance()`/`getInstance()` accessor. Read [examples, options, and analysis boundaries](rules/no_singleton_access.md).

### no_reflection

Replace reflective construction with explicit factories, mapping, or framework conventions. Read [examples, options, and analysis boundaries](rules/no_reflection.md).

### no_runtime_hooks

Do not install global runtime hooks from analyzed code; keep lifecycle and error handling in infrastructure configured outside the analyzed paths. Read [examples, options, and analysis boundaries](rules/no_runtime_hooks.md).

### no_runtime_code_evaluation

Represent policy as typed code or validated data instead of evaluating generated source with `eval`. Read [examples, options, and analysis boundaries](rules/no_runtime_code_evaluation.md).

### no_process_configuration_mutation

Do not mutate ini settings, working directory, locale, or timezone from analyzed code; configure the runtime through `php.ini` or deployment configuration. Read [examples, options, and analysis boundaries](rules/no_process_configuration_mutation.md).

### no_direct_response_globals

Reports `header()`, `setcookie()`, `setrawcookie()`, and `session_start()` calls that mutate response or session state outside an explicit boundary. Read [examples, options, and analysis boundaries](rules/no_direct_response_globals.md).

### no_symbol_table_mutation

Map named input fields explicitly instead of manufacturing local variables with `extract`. Read [examples, options, and analysis boundaries](rules/no_symbol_table_mutation.md).

### no_implicit_arguments

Reports `func_get_args()`, `func_get_arg()`, and `func_num_args()` calls. Read [examples, options, and analysis boundaries](rules/no_implicit_arguments.md).

### no_direct_filesystem_mutation

Reports direct file writes, deletes, moves, and directory creation calls in application code. Read [examples, options, and analysis boundaries](rules/no_direct_filesystem_mutation.md).

### no_subprocess_execution

Wrap external commands in an injected, typed gateway with explicit failures instead of calling `shell_exec`, `exec`, `system`, or `popen` inline. Read [examples, options, and analysis boundaries](rules/no_subprocess_execution.md).

### no_native_deserialization

Reports `unserialize()` calls. Read [examples, options, and analysis boundaries](rules/no_native_deserialization.md).

### no_runtime_includes

Use autoloaded collaborators or validated data configuration instead of including files at runtime. Read [examples, options, and analysis boundaries](rules/no_runtime_includes.md).

### no_runtime_type_aliases

Migrate callers to the real type or declare a visible adapter instead of aliasing class names at runtime with `class_alias`. Read [examples, options, and analysis boundaries](rules/no_runtime_type_aliases.md).

### no_dynamic_properties

Reports the `#[AllowDynamicProperties]` attribute that licenses undeclared object state. Read [examples, options, and analysis boundaries](rules/no_dynamic_properties.md).

### no_anonymous_classes

Promote anonymous classes to named, independently testable types. Read [examples, options, and analysis boundaries](rules/no_anonymous_classes.md).

### no_object_cloning

Construct a new value instead of copying an object with `clone`. Read [examples, options, and analysis boundaries](rules/no_object_cloning.md).

### no_closure_rebinding

Forbid `Closure::bind()` and `bindTo()`; pass an explicit collaborator instead of changing a closure scope at runtime. Read [examples, options, and analysis boundaries](rules/no_closure_rebinding.md).

### no_runtime_contract_probes

Require an interface or typed value instead of discovering capabilities at runtime with `method_exists`, `property_exists`, or `is_callable`. Read [examples, options, and analysis boundaries](rules/no_runtime_contract_probes.md).

### no_global_state

Reports `global` statements and `$GLOBALS` access. Read [examples, options, and analysis boundaries](rules/no_global_state.md).

### no_environment_reads

Reports `env()`, `getenv()`, `putenv()`, and `$_ENV`/`$_SERVER` access outside explicitly configured configuration boundaries. Read [examples, options, and analysis boundaries](rules/no_environment_reads.md).

### no_error_suppression

Reports the `@` error-suppression operator. Read [examples, options, and analysis boundaries](rules/no_error_suppression.md).

### no_dynamic_invocation

Reports indirect callable helpers and method calls whose name is selected at runtime. Read [examples, options, and analysis boundaries](rules/no_dynamic_invocation.md).

### no_catch_all_exceptions

Make broad `catch (Throwable)` / `catch (Exception)` blocks end with an explicit outcome: return a value, throw, or terminate the process. Read [examples, options, and analysis boundaries](rules/no_catch_all_exceptions.md).

### no_boolean_parameters

Replace boolean parameters that select behavior with named methods or readonly value state. Read [examples, options, and analysis boundaries](rules/no_boolean_parameters.md).

### no_behavior_magic_methods

Replace `__call`, `__callStatic`, `__get`, `__set`, `__isset`, and `__unset` with explicit methods and contracts. Read [examples, options, and analysis boundaries](rules/no_behavior_magic_methods.md).

### no_static_mutable_properties

Put shared mutable state in an injected object with explicit lifetime and ownership instead of static properties. Read [examples, options, and analysis boundaries](rules/no_static_mutable_properties.md).

### no_blocking_sleep

Replace blocking sleep calls with injected time, scheduler, retry, or backoff coordination. Read [examples, options, and analysis boundaries](rules/no_blocking_sleep.md).

### max_return_statements

Limits the number of `return` statements a single method may contain. Read [examples, options, and analysis boundaries](rules/max_return_statements.md).

### max_boolean_conditions

Limits the number of boolean operands a single condition inside a method may combine. Read [examples, options, and analysis boundaries](rules/max_boolean_conditions.md).

### Research cohesion facts

Observed method components and public TCC counts are available for research.
The proposed disconnected-groups threshold was rejected after inspecting real
fluent objects, concerns and protocol implementations; it is not a built-in rule.
Constructor/static/abstract exclusions, trait composition and unknown cases are
specified in [Research Metrics](RESEARCH_METRICS.md).

### no_trivial_factories

Reports methods whose only work is constructing an object and forwarding all parameters unchanged. Read [examples, options, and analysis boundaries](rules/no_trivial_factories.md).

## Strict composition and behavior checks

These checks are included in the standard catalog. An explicit `with([...])` list keeps
its selected rules; add the checks below to use them in an existing explicit configuration.
Diagnostics describe observed syntax and resolved project facts, not the author's intent.

### max_effective_methods_per_class

Limits the method count of a class including the methods contributed by its traits. Read [examples, options, and analysis boundaries](rules/max_effective_methods_per_class.md).

### max_effective_class_length

Limits the source length of a class counting the source of all composed traits once. Read [examples, options, and analysis boundaries](rules/max_effective_class_length.md).

### max_delegation_depth

Limits consecutive transparent forwarding methods, rather than the size of each individual method. Read [examples, options, and analysis boundaries](rules/max_delegation_depth.md).

### no_mode_parameters

Reports parameters whose value selects between distinct operations inside a callable. Read [examples, options, and analysis boundaries](rules/no_mode_parameters.md).

### no_repeated_type_dispatch

Centralize repeated dispatch on the same type or discriminator family behind one operation boundary. Read [examples, options, and analysis boundaries](rules/no_repeated_type_dispatch.md).

### no_untyped_parameters

Declare a native type on every parameter, using `mixed` to preserve an untyped inherited contract. Read [examples, options, and analysis boundaries](rules/no_untyped_parameters.md).

### no_unused_parameters

Reports function and method parameters that are never used. Read [examples, options, and analysis boundaries](rules/no_unused_parameters.md).

### no_unused_private_state

Removes private properties and constants that are never read, including composed trait state. Read [examples, options, and analysis boundaries](rules/no_unused_private_state.md).

### no_empty_local_interfaces

Reports local interfaces whose complete declared and inherited contract has no methods or properties. Read [examples, options, and analysis boundaries](rules/no_empty_local_interfaces.md).

### no_redundant_local_interfaces

Require a type consumer for local interfaces, or declare their external consumers explicitly in contracts. Read [examples, options, and analysis boundaries](rules/no_redundant_local_interfaces.md).

### no_dependency_cycles

Detects module dependency cycles and required constructor cycles, with concrete paths and file evidence. Read [examples, options, and analysis boundaries](rules/no_dependency_cycles.md).

### Laravel audit corrections

See the [Laravel audit](research/laravel-audit/README.md) for verified examples.
Ask-then-tell checks exclude consumed ternary results and decisions about the
receiver's own state (`$this`, `self`, `static`). Inline Markdown code examples
are not treated as commented-out PHP; surrounding disabled code remains checked.

### Registered Laravel middleware inputs

`no_unused_parameters` preserves the first two positions of a public, non-static
`terminate` method when its exact class is registered by a Laravel ServiceProvider
through `$this->app['router']->aliasMiddleware(...)`. The project pass resolves
registration across files. A class or method name alone is not evidence; extra
inputs and unrelated registrations remain checked.

### no_temporary_return_variable

Forbids a local variable created only to return its value immediately. Read [examples, options, and analysis boundaries](rules/no_temporary_return_variable.md).

### no_consecutive_block_comments

Reports adjacent block comments that should be merged into a single comment with the description and PHPDoc tags together. Read [examples, options, and analysis boundaries](rules/no_consecutive_block_comments.md).

## Owner behavior recommendations (standard)

These checks suggest concerns, behavior on its owner, and fluent scenarios using
concrete structural evidence. See [definitions, examples, and boundaries](OWNER_BEHAVIOR_RULES.md).

### fragmented_owner_behavior

Finds several stateless operation classes that all interpret or change the same local owner, and suggests moving that behavior onto the owner. Read [examples, options, and analysis boundaries](rules/fragmented_owner_behavior.md).

### repeated_owner_concern

Finds an identical group of stateful methods copied across independent owners and suggests sharing it as a concern (trait). Read [examples, options, and analysis boundaries](rules/repeated_owner_concern.md).

### scattered_owner_concern

Reports a cohesive stateful method group that is interleaved with unrelated methods in one owner and suggests grouping it together, possibly as a concern. Read [examples, options, and analysis boundaries](rules/scattered_owner_concern.md).

### repeated_configuration_sequence

Finds the same fresh-object setup sequence repeated across consumers and suggests a fluent object or named construction that expresses the scenario. Read [examples, options, and analysis boundaries](rules/repeated_configuration_sequence.md).

### repeated_foreign_state_decision

Finds the same compound question about another object's state repeated in several consumers and suggests naming it as a predicate on the owner. Read [examples, options, and analysis boundaries](rules/repeated_foreign_state_decision.md).

### repeated_foreign_state_update

Finds the same arithmetic update of another object's numeric state repeated in several consumers and suggests naming it as an operation on the owner. Read [examples, options, and analysis boundaries](rules/repeated_foreign_state_update.md).

### forbidden_dependencies (opt-in)

Enforce directed namespace boundaries: namespaces declared as source may not depend on the target namespaces you forbid. Read [examples, options, and analysis boundaries](rules/forbidden_dependencies.md).

### no_reassigned_parameters

Rejects direct rebinding of by-value input parameters. Read [examples, options, and analysis boundaries](rules/no_reassigned_parameters.md).

### representation_churn

Flags value paths where a primitive is extracted from a representation object, processed, and reconstructed into the same representation, for inspection before changing its representation. Read [examples, options, and analysis boundaries](rules/representation_churn.md).

### no_static_only_class

Reports classes whose declared surface is entirely static. Read [examples, options, and analysis boundaries](rules/no_static_only_class.md).

### prefer_includes

Reports strpos comparisons that ask the substring question. Read [examples, options, and analysis boundaries](rules/prefer_includes.md).

### prefer_string_starts_ends_with

Reports substr comparisons that ask the prefix or suffix question. Read [examples, options, and analysis boundaries](rules/prefer_string_starts_ends_with.md).

## Example selected policy

This example checks a selected set of policies. It does not add them to the
standard bundle. Use `soda init` when you want an expanded standard configuration
and edit individual rule arguments there.

```php
<?php

use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Complexity\NoAssignmentInCondition;
use Cosmira\Soda\Rules\Complexity\NoComplexControlConditions;
use Cosmira\Soda\Rules\Complexity\NoElseBranches;
use Cosmira\Soda\Rules\Documentation\MultilineConstantPhpDoc;
use Cosmira\Soda\Rules\Structure\NoTrivialDelegatingClasses;
use Cosmira\Soda\Rules\Usage\NoNumericArrayIndex;
use Cosmira\Soda\Rules\Usage\NoUnusedMethods;
use Cosmira\Soda\Rules\Usage\OnlyListArraysAllowed;
use Cosmira\Soda\Rules\Usage\UselessVariableRule;

return Soda::configure()
    ->withPaths([__DIR__.'/src'])
    ->with([
        new NoAssignmentInCondition(),
        new NoComplexControlConditions(),
        new NoElseBranches(),
        new MultilineConstantPhpDoc(['public']),
        new NoTrivialDelegatingClasses(),
        new OnlyListArraysAllowed(),
        new NoNumericArrayIndex(),
        new NoUnusedMethods(ignore: ['seedDatabase']),
        new UselessVariableRule(),
    ]);
```
