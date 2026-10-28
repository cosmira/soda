# Rule reference

Find a rule by topic below, or run `vendor/bin/soda list:rules` to inspect the
installed catalog. Each rule page is the reference for its behavior, constructor
options, examples, and exceptions.

**Enabled by default** states whether the standard bundle selects the rule.
**Affects quality result when enabled** states whether its violations can fail a
check after you select it. Research checks produce separate evidence and never
fail the quality check, even when enabled by default. A metric's research origin
does not make it a research check.

See [Configuration](../SODA_PHP_CONFIG.md) to choose rules or tune thresholds.
For a new built-in rule, follow the [authoring guide](../ADDING_A_QUALITY_RULE.md#document-the-rule).

## Topics

These topics group documentation for reading. They are not catalog section names
for `RuleCatalog::checks()`. Its sections are `structural`, `complexity`, and
`naming`; use `list:rules` to see each rule's catalog section.

- [Architecture](#architecture)
- [Complexity](#complexity)
- [Structure](#structure)
- [Usage](#usage)
- [Design](#design)
- [Naming](#naming)
- [Documentation](#documentation)

| Topic | Focus |
| --- | --- |
| Architecture | Hidden coupling, runtime magic, framework bypasses |
| Complexity | Control-flow and expression complexity |
| Structure | Size limits, file and namespace shape, ordering |
| Usage | Dead code, redundant variables and parameters, array usage |
| Design | Behavioral cohesion and duplication across owners |
| Naming | Identifier length and redundancy |
| Documentation | PHPDoc and comment hygiene |

## Rule index


### Architecture

| Rule | Description | Enabled by default | Affects quality result when enabled |
| --- | --- | --- | --- |
| [forbidden_dependencies](forbidden_dependencies.md) | Forbidden module dependencies | no | yes |
| [no_anonymous_classes](no_anonymous_classes.md) | Anonymous classes | yes | yes |
| [no_behavior_magic_methods](no_behavior_magic_methods.md) | Behavior magic methods | yes | yes |
| [no_blocking_sleep](no_blocking_sleep.md) | Blocking sleeps | yes | yes |
| [no_boolean_parameters](no_boolean_parameters.md) | Boolean parameters | no | yes |
| [no_catch_all_exceptions](no_catch_all_exceptions.md) | Catch-all exceptions | yes | yes |
| [no_closure_rebinding](no_closure_rebinding.md) | Closure rebinding | yes | yes |
| [no_dependency_cycles](no_dependency_cycles.md) | Module dependency cycles | yes | yes |
| [no_direct_filesystem_mutation](no_direct_filesystem_mutation.md) | Direct filesystem mutation | yes | yes |
| [no_direct_response_globals](no_direct_response_globals.md) | Direct response globals | yes | yes |
| [no_dynamic_invocation](no_dynamic_invocation.md) | Dynamic invocations | yes | yes |
| [no_dynamic_properties](no_dynamic_properties.md) | Dynamic properties | yes | yes |
| [no_empty_local_interfaces](no_empty_local_interfaces.md) | Empty local interfaces | yes | yes |
| [no_environment_reads](no_environment_reads.md) | Environment reads | yes | yes |
| [no_error_suppression](no_error_suppression.md) | Suppressed errors | yes | yes |
| [no_global_state](no_global_state.md) | Global state access | yes | yes |
| [no_implicit_arguments](no_implicit_arguments.md) | Implicit arguments | yes | yes |
| [no_mode_parameters](no_mode_parameters.md) | Mode parameters | yes | yes |
| [no_native_deserialization](no_native_deserialization.md) | Native deserialization | yes | yes |
| [no_object_cloning](no_object_cloning.md) | Object cloning | no | yes |
| [no_process_configuration_mutation](no_process_configuration_mutation.md) | Process configuration mutation | yes | yes |
| [no_redundant_local_interfaces](no_redundant_local_interfaces.md) | Unconsumed local interfaces | yes | yes |
| [no_reflection](no_reflection.md) | Reflection usage | yes | yes |
| [no_repeated_type_dispatch](no_repeated_type_dispatch.md) | Repeated type dispatch | yes | yes |
| [no_runtime_code_evaluation](no_runtime_code_evaluation.md) | Runtime code evaluation | yes | yes |
| [no_runtime_contract_probes](no_runtime_contract_probes.md) | Runtime contract probes | no | yes |
| [no_runtime_hooks](no_runtime_hooks.md) | Runtime hooks | yes | yes |
| [no_runtime_includes](no_runtime_includes.md) | Runtime includes | yes | yes |
| [no_runtime_type_aliases](no_runtime_type_aliases.md) | Runtime type aliases | yes | yes |
| [no_service_locator_calls](no_service_locator_calls.md) | Service locator calls | no | yes |
| [no_singleton_access](no_singleton_access.md) | Singleton access | yes | yes |
| [no_static_mutable_properties](no_static_mutable_properties.md) | Static mutable state | no | yes |
| [no_subprocess_execution](no_subprocess_execution.md) | Subprocess execution | yes | yes |
| [no_symbol_table_mutation](no_symbol_table_mutation.md) | Symbol-table mutation | yes | yes |
| [no_untyped_parameters](no_untyped_parameters.md) | Untyped parameters | yes | yes |

### Complexity

| Rule | Description | Enabled by default | Affects quality result when enabled |
| --- | --- | --- | --- |
| [max_boolean_conditions](max_boolean_conditions.md) | Boolean conditions | yes | yes |
| [max_cognitive_complexity](max_cognitive_complexity.md) | Cognitive complexity | yes | yes |
| [max_consecutive_local_assignments](max_consecutive_local_assignments.md) | Consecutive initial local assignments | yes | yes |
| [max_control_nesting](max_control_nesting.md) | Control structure nesting | yes | yes |
| [max_cyclomatic_complexity](max_cyclomatic_complexity.md) | Cyclomatic complexity | yes | yes |
| [max_return_statements](max_return_statements.md) | Return statements | no | yes |
| [max_statements_per_callable](max_statements_per_callable.md) | Executable statements per callable | yes | yes |
| [max_try_catch_blocks](max_try_catch_blocks.md) | Try/catch blocks | yes | yes |
| [no_assignment_in_condition](no_assignment_in_condition.md) | Assignments inside conditions | yes | yes |
| [no_assignment_in_return](no_assignment_in_return.md) | Assignments inside returns | yes | yes |
| [no_chained_assignments](no_chained_assignments.md) | Chained assignments | yes | yes |
| [no_complex_control_conditions](no_complex_control_conditions.md) | Complex control conditions | yes | yes |
| [no_declarations_before_early_exit](no_declarations_before_early_exit.md) | Declarations before early exits | yes | yes |
| [no_else_branches](no_else_branches.md) | Else/elseif branches | yes | yes |
| [no_empty_finally_blocks](no_empty_finally_blocks.md) | Empty finally blocks | yes | yes |
| [no_identical_branches](no_identical_branches.md) | Identical expression branches | yes | yes |
| [no_nested_ternary](no_nested_ternary.md) | Nested full ternaries | yes | yes |
| [no_redundant_boolean_comparisons](no_redundant_boolean_comparisons.md) | Redundant boolean comparisons | yes | yes |
| [no_redundant_boolean_returns](no_redundant_boolean_returns.md) | Redundant boolean returns | yes | yes |
| [no_redundant_rethrow](no_redundant_rethrow.md) | Redundant rethrow | no | yes |
| [no_repeated_compound_conditions](no_repeated_compound_conditions.md) | Methods repeating a compound condition | yes | yes |
| [no_repeated_string_literals](no_repeated_string_literals.md) | Repeated expression string literals | yes | yes |
| [no_return_in_finally](no_return_in_finally.md) | Returns inside finally | yes | yes |
| [no_unneeded_ternary](no_unneeded_ternary.md) | Unneeded boolean ternaries | yes | yes |
| [no_useless_concat](no_useless_concat.md) | Adjacent string literal concatenations | yes | yes |
| [no_useless_return](no_useless_return.md) | Redundant final bare returns | yes | yes |
| [prefer_combined_conditions](prefer_combined_conditions.md) | Nested conditions that can be combined | yes | yes |
| [prefer_combined_guards](prefer_combined_guards.md) | Split identical guards | yes | yes |
| [prefer_continue](prefer_continue.md) | Loops ending in a wrapped remainder | yes | yes |
| [prefer_null_coalesce_assignment](prefer_null_coalesce_assignment.md) | Repeated null-coalesce targets | yes | yes |

### Design

| Rule | Description | Enabled by default | Affects quality result when enabled |
| --- | --- | --- | --- |
| [fragmented_owner_behavior](fragmented_owner_behavior.md) | Fragmented owner behavior | yes | yes |
| [repeated_configuration_sequence](repeated_configuration_sequence.md) | Repeated object configuration | yes | yes |
| [repeated_foreign_state_decision](repeated_foreign_state_decision.md) | Repeated foreign state decisions | yes | yes |
| [repeated_foreign_state_update](repeated_foreign_state_update.md) | Repeated foreign state updates | yes | yes |
| [repeated_owner_concern](repeated_owner_concern.md) | Copied owner concerns | yes | yes |
| [representation_churn](representation_churn.md) | Representation research candidates | yes | no |
| [scattered_owner_concern](scattered_owner_concern.md) | Scattered owner concerns | yes | yes |

### Documentation

| Rule | Description | Enabled by default | Affects quality result when enabled |
| --- | --- | --- | --- |
| [multiline_constant_phpdoc](multiline_constant_phpdoc.md) | Multiline constant PHPDoc | yes | yes |
| [multiline_method_phpdoc](multiline_method_phpdoc.md) | Multiline method PHPDoc | yes | yes |
| [multiline_property_phpdoc](multiline_property_phpdoc.md) | Multiline property PHPDoc | yes | yes |
| [no_consecutive_block_comments](no_consecutive_block_comments.md) | Consecutive block comments | yes | yes |
| [no_vacuous_docblocks](no_vacuous_docblocks.md) | Vacuous PHPDoc | no | yes |

### Naming

| Rule | Description | Enabled by default | Affects quality result when enabled |
| --- | --- | --- | --- |
| [avoid_redundant_naming](avoid_redundant_naming.md) | Redundant naming | yes | yes |
| [boolean_methods_without_prefix](boolean_methods_without_prefix.md) | Boolean method prefixes | no | yes |
| [class_name_length](class_name_length.md) | Class name length | yes | yes |
| [method_name_length](method_name_length.md) | Method name length | yes | yes |
| [namespace_name_length](namespace_name_length.md) | Namespace name length | yes | yes |
| [no_dumpster_suffixes](no_dumpster_suffixes.md) | Generic type suffixes | no | yes |
| [variable_name_length](variable_name_length.md) | Variable name length | yes | yes |

### Structure

| Rule | Description | Enabled by default | Affects quality result when enabled |
| --- | --- | --- | --- |
| [max_arguments](max_arguments.md) | Arguments | no | yes |
| [max_ask_then_tell_patterns](max_ask_then_tell_patterns.md) | Ask-then-tell patterns | yes | yes |
| [max_class_length](max_class_length.md) | Class length | yes | yes |
| [max_classes_per_file](max_classes_per_file.md) | Classes per file | yes | yes |
| [max_classes_per_namespace](max_classes_per_namespace.md) | Classes per namespace | yes | yes |
| [max_classes_per_project](max_classes_per_project.md) | Classes per project | yes | yes |
| [max_commented_out_code_lines](max_commented_out_code_lines.md) | Commented-out code lines | yes | yes |
| [max_delegation_depth](max_delegation_depth.md) | Delegation depth | yes | yes |
| [max_dependencies](max_dependencies.md) | Dependencies | yes | yes |
| [max_effective_class_length](max_effective_class_length.md) | Source lines including traits | yes | yes |
| [max_effective_methods_per_class](max_effective_methods_per_class.md) | Methods including traits | yes | yes |
| [max_efferent_coupling](max_efferent_coupling.md) | Efferent coupling (Ce) | yes | yes |
| [max_empty_catch_blocks](max_empty_catch_blocks.md) | Empty catch blocks | yes | yes |
| [max_file_loc](max_file_loc.md) | File LOC | yes | yes |
| [max_interfaces_per_class](max_interfaces_per_class.md) | Interfaces per class | yes | yes |
| [max_layer_dominance_percentage](max_layer_dominance_percentage.md) | Layer mixing | yes | yes |
| [max_line_length](max_line_length.md) | Line length | yes | yes |
| [max_method_length](max_method_length.md) | Method length | yes | yes |
| [max_methods_per_class](max_methods_per_class.md) | Methods per class | yes | yes |
| [max_namespace_depth](max_namespace_depth.md) | Namespace depth | yes | yes |
| [max_properties_per_class](max_properties_per_class.md) | Properties per class | no | yes |
| [max_public_methods](max_public_methods.md) | Public methods | yes | yes |
| [max_todo_fixme_comments](max_todo_fixme_comments.md) | TODO/FIXME comments | yes | yes |
| [max_traits_per_class](max_traits_per_class.md) | Traits per class | yes | yes |
| [methods_follow_call_order](methods_follow_call_order.md) | Methods out of call order | yes | yes |
| [no_static_only_class](no_static_only_class.md) | Static-only classes | no | yes |
| [no_trivial_delegating_classes](no_trivial_delegating_classes.md) | Trivial delegating classes | yes | yes |
| [no_trivial_factories](no_trivial_factories.md) | Trivial factories | yes | yes |

### Usage

| Rule | Description | Enabled by default | Affects quality result when enabled |
| --- | --- | --- | --- |
| [no_numeric_array_index](no_numeric_array_index.md) | Numeric array index | yes | yes |
| [no_reassigned_parameters](no_reassigned_parameters.md) | Rebound input parameters | yes | yes |
| [no_temporary_return_variable](no_temporary_return_variable.md) | Temporary return variables | yes | yes |
| [no_unused_parameters](no_unused_parameters.md) | Unused parameters | yes | yes |
| [no_unused_private_state](no_unused_private_state.md) | Unread private state | yes | yes |
| [only_list_arrays](only_list_arrays.md) | Nested array chains (list-only / strict opt-in) | yes | yes |
| [prefer_includes](prefer_includes.md) | Position checks for substrings | yes | yes |
| [prefer_string_starts_ends_with](prefer_string_starts_ends_with.md) | Substring checks for prefixes and suffixes | yes | yes |
| [unused_methods](unused_methods.md) | Unused methods | yes | yes |
| [useless_variable](useless_variable.md) | Useless variable | yes | yes |

## Additional opt-in rules

Some checks are configured directly rather than registered in the built-in
catalog. They do not appear in `list:rules`.

- [max_variable_name_words](max_variable_name_words.md) limits words in local
  variable and parameter names when a team chooses that naming policy.
