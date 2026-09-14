# Adversarial Soda iterations

The demo is intentionally working code with hidden dependencies and fragile architecture.

The hardened configuration loads `RuleCatalog::standard()`, so all 69 catalog rules run together. `rule-challenges.php` assigns every one of those rules a concrete evasion attempt and one of the ten review iterations; a catalog-contract test fails if a rule is added without an attack. The executable numbered snapshots are focused regressions for the ten attacks that exposed new architecture blind spots.

```bash
php demo-app/run.php
php soda q demo-app --exclude=iterations --config=demo-app/baseline-soda.php
php soda q demo-app --exclude=iterations --config=demo-app/soda.php
```

| Iteration | Stubborn workaround that passed the baseline | Soda hardening |
|---:|---|---|
| 1 | Pull a dependency through `app()` so constructors stay “clean” | `no_service_locator_calls` |
| 2 | Share an audit list through `global` | `no_global_state` |
| 3 | Read deployment configuration with `getenv()` in domain behavior | `no_environment_reads` |
| 4 | Prefix a fallible read with `@` and call the fallback “resilience” | `no_error_suppression` |
| 5 | Store a first-class callable and invoke it dynamically | `no_dynamic_invocation` |
| 6 | Catch `Throwable` and convert every failure into success | `no_catch_all_exceptions` |
| 7 | Add a boolean mode parameter instead of naming two behaviors | `no_boolean_parameters` |
| 8 | Manufacture an API through `__get()` | `no_behavior_magic_methods` |
| 9 | Keep process-wide counters in a static property | `no_static_mutable_properties` |
| 10 | Coordinate work with `usleep()` inside application behavior | `no_blocking_sleep` |
| 11 | Route a command through `call_user_func()` so the call looks named | strengthen `no_dynamic_invocation` |
| 12 | Call a mode flag a three-state policy by typing it as `?bool` | strengthen `no_boolean_parameters` |
| 13 | Read tenant metadata from `$_SERVER` instead of `$_ENV` | strengthen `no_environment_reads` |
| 14 | Rename blocking as a nanosecond consistency budget | strengthen `no_blocking_sleep` |
| 15 | Move a process cache from a static property into a static local | strengthen `no_static_mutable_properties` |
| 16 | Replace a service locator with a canonical `Manager::instance()` | `no_singleton_access` |
| 17 | Construct framework objects through a metadata hydrator | `no_reflection` |
| 18 | Flush audit state from a shutdown callback | `no_runtime_hooks` |
| 19 | Temporarily replace PHP's error handler inside import behavior | strengthen `no_runtime_hooks` |
| 20 | Compile tenant policy with generated PHP source | `no_runtime_code_evaluation` |
| 21 | Write deployment configuration with `putenv()` | strengthen `no_environment_reads` |
| 22 | Apply commercial precision through `ini_set()` | `no_process_configuration_mutation` |
| 23 | Establish an implicit storage root with `chdir()` | strengthen `no_process_configuration_mutation` |
| 24 | Install a worker-wide numeric locale | strengthen `no_process_configuration_mutation` |
| 25 | Install a tenant reporting timezone globally | strengthen `no_process_configuration_mutation` |
| 26 | Emit cache policy directly with `header()` | `no_direct_response_globals` |
| 27 | Persist tenant affinity with `setcookie()` | strengthen `no_direct_response_globals` |
| 28 | Start session lifecycle inside checkout | strengthen `no_direct_response_globals` |
| 29 | Hydrate context into local variables with `extract()` | `no_symbol_table_mutation` |
| 30 | Recover undeclared command inputs with `func_get_args()` | `no_implicit_arguments` |
| 31 | Persist a projection directly with `file_put_contents()` | `no_direct_filesystem_mutation` |
| 32 | Delete an export directly with `unlink()` | strengthen `no_direct_filesystem_mutation` |
| 33 | Publish projections atomically with `rename()` | strengthen `no_direct_filesystem_mutation` |
| 34 | Provision tenant storage with `mkdir()` during a request | strengthen `no_direct_filesystem_mutation` |
| 35 | Delegate formatting through `shell_exec()` | `no_subprocess_execution` |
| 36 | Wrap `exec()` in a compatibility gateway | strengthen `no_subprocess_execution` |
| 37 | Stream native output through `system()` | strengthen `no_subprocess_execution` |
| 38 | Rebrand `popen()` as a lightweight transport | strengthen `no_subprocess_execution` |
| 39 | Decode a trusted broker envelope with `unserialize()` | `no_native_deserialization` |
| 40 | Load executable policy with a colocated `include` | `no_runtime_includes` |
| 41 | Preserve legacy types with `class_alias()` | `no_runtime_type_aliases` |
| 42 | Hydrate partner data through dynamic properties | `no_dynamic_properties` |
| 43 | Hide a strategy in an anonymous class | `no_anonymous_classes` |
| 44 | Construct convention types through `new $class` | strengthen `no_dynamic_invocation` |
| 45 | Fork configured prototypes through `clone` | `no_object_cloning` |
| 46 | Change policy scope with `Closure::bind()` | `no_closure_rebinding` |
| 47 | Rebrand `bindTo()` as policy portability | strengthen `no_closure_rebinding` |
| 48 | Negotiate gateways with `method_exists()` | `no_runtime_contract_probes` |
| 49 | Discover payload shape with `property_exists()` | strengthen `no_runtime_contract_probes` |
| 50 | Admit handlers through `is_callable()` | strengthen `no_runtime_contract_probes` |

## Iteration records

### Итерация 1

Обход: dependency lookup through `app()` disguised as a clean constructor.
Почему SODA не поймала: control-flow rules did not inspect dependency acquisition.
Новое/изменённое правило: `no_service_locator_calls` forbids `app()` and `resolve()` calls.
Изменения в тестах: snapshot 01 must run, pass the empty prior set, and fail this rule once.

### Итерация 2

Обход: a shared event list passed through `global` after service locators were forbidden.
Почему SODA не поймала: explicit global-variable access was outside the first rule's function-call scope.
Новое/изменённое правило: `no_global_state` rejects `global` and `$GLOBALS` access.
Изменения в тестах: snapshot 02 must pass iteration 1's rule and fail only the new rule.

### Итерация 3

Обход: domain behavior reads deployment state directly with `getenv()`.
Почему SODA не поймала: environment state is hidden input without using PHP global syntax.
Новое/изменённое правило: `no_environment_reads` rejects `env()`, `getenv()`, and `$_ENV` access.
Изменения в тестах: snapshot 03 must pass rules 1–2 and expose one environment read.

### Итерация 4

Обход: `@file_get_contents()` is presented as a resilient optional repository.
Почему SODA не поймала: suppressing diagnostics is neither global nor environment access.
Новое/изменённое правило: `no_error_suppression` rejects PHP's `@` operator.
Изменения в тестах: snapshot 04 must pass rules 1–3 and expose one suppression node.

### Итерация 5

Обход: a first-class callable is stored and dynamically invoked as a “message bus”.
Почему SODA не поймала: the invoked behavior is no longer visible as a named call.
Новое/изменённое правило: `no_dynamic_invocation` rejects dynamic function, method, and static calls.
Изменения в тестах: snapshot 05 must pass rules 1–4 and expose one dynamic call.

### Итерация 6

Обход: `catch (Throwable)` converts every defect into an apparent success.
Почему SODA не поймала: earlier rules saw neither hidden input nor dynamic dispatch in broad recovery.
Новое/изменённое правило: `no_catch_all_exceptions` rejects catches of `Throwable` and base `Exception`.
Изменения в тестах: snapshot 06 must pass rules 1–5 and expose one catch-all.

### Итерация 7

Обход: a boolean argument silently selects a second behavior inside one method.
Почему SODA не поймала: the mode switch uses a legal parameter and no forbidden dependency mechanism.
Новое/изменённое правило: `no_boolean_parameters` requires separately named operations.
Изменения в тестах: snapshot 07 must pass rules 1–6 and expose one boolean parameter.

### Итерация 8

Обход: `__get()` manufactures an undocumented fluent API at runtime.
Почему SODA не поймала: behavior is hidden behind property syntax rather than a dynamic call expression.
Новое/изменённое правило: `no_behavior_magic_methods` rejects behavioral magic APIs.
Изменения в тестах: snapshot 08 must pass rules 1–7 and expose one forbidden magic method.

### Итерация 9

Обход: a process-wide identity map hides mutable state in a static property.
Почему SODA не поймала: it avoids both `global` and `$GLOBALS` while retaining the same lifetime problem.
Новое/изменённое правило: `no_static_mutable_properties` rejects static properties.
Изменения в тестах: snapshot 09 must pass rules 1–8 and expose one static property.

### Итерация 10

Обход: `usleep()` is renamed “eventual-consistency coordination”.
Почему SODA не поймала: blocking time is an implicit side effect distinct from all earlier constructs.
Новое/изменённое правило: `no_blocking_sleep` rejects `sleep()` and `usleep()` in application code.
Изменения в тестах: snapshot 10 must pass rules 1–9 and expose one blocking call.

### Итерация 11

Обход: a convention-driven command bus invokes its handler through `call_user_func()`.
Почему SODA не поймала: `no_dynamic_invocation` inspected dynamic AST names, while the helper itself has a static name.
Новое/изменённое правило: `no_dynamic_invocation` also rejects `call_user_func*()` and `forward_static_call*()`.
Изменения в тестах: snapshot 11 must execute, pass the other 60 catalog rules, and expose exactly one indirect helper dispatch.

### Итерация 12

Обход: a delivery manager accepts `?bool` and markets `null` as inherited policy.
Почему SODA не поймала: `no_boolean_parameters` matched only a direct `Identifier('bool')` type node.
Новое/изменённое правило: `no_boolean_parameters` recursively rejects bool inside nullable and union types.
Изменения в тестах: snapshot 12 must pass the other catalog rules before hardening and then expose exactly one nullable boolean parameter.

### Итерация 13

Обход: tenant resolution reads `$_SERVER` and calls it infrastructure metadata rather than environment state.
Почему SODA не поймала: `no_environment_reads` covered `$_ENV`, `env()`, and `getenv()`, but not the parallel server superglobal.
Новое/изменённое правило: `no_environment_reads` rejects both `$_ENV` and `$_SERVER` access outside excluded boundaries.
Изменения в тестах: snapshot 13 proves the old full catalog was silent and the strengthened rule reports one environment read.

### Итерация 14

Обход: a replica coordinator blocks with `time_nanosleep()` and presents it as precise latency budgeting.
Почему SODA не поймала: `no_blocking_sleep` originally recognized only `sleep()` and `usleep()`.
Новое/изменённое правило: `no_blocking_sleep` also rejects `time_nanosleep()` and `time_sleep_until()`.
Изменения в тестах: snapshot 14 executes normally, passes the catalog without the strengthened checker, and yields one blocking violation afterward.

### Итерация 15

Обход: an exchange-rate repository hides its worker-lifetime cache in a method-local `static` variable.
Почему SODA не поймала: `no_static_mutable_properties` inspected class properties but ignored PHP static locals with the same lifetime problem.
Новое/изменённое правило: `no_static_mutable_properties` now rejects both static properties and static local storage; its message and label describe the shared-state problem rather than one syntax.
Изменения в тестах: snapshot 15 proves the cache passed all previous checks, keeps producing a rate, and is now reported exactly once.

### Итерация 16

Обход: a global feature manager exposes `instance()` as a harmless canonical factory and then resolves capabilities behind it.
Почему SODA не поймала: the service-locator rule recognized framework helpers, while singleton access encoded the same hidden construction in a static method.
Новое/изменённое правило: `no_singleton_access` rejects static `instance()` and `getInstance()` acquisition.
Изменения в тестах: snapshot 16 proves the manager works and passed the previous catalog, then requires one singleton-access finding.

### Итерация 17

Обход: a metadata hydrator constructs arbitrary framework objects with `ReflectionClass` and bypasses constructors.
Почему SODA не поймала: dynamic-call checks inspected invocation syntax, not named reflection APIs that erase construction contracts.
Новое/изменённое правило: `no_reflection` rejects construction of PHP reflection objects.
Изменения в тестах: snapshot 17 runs against `stdClass`, passed the previous catalog, and now produces one reflection finding.

### Итерация 18

Обход: an audit buffer registers its own flush method with `register_shutdown_function()`.
Почему SODA не поймала: the callback is first-class and the registration function has a static name, while its side effect occurs outside the visible workflow.
Новое/изменённое правило: `no_runtime_hooks` initially rejects shutdown callback registration.
Изменения в тестах: snapshot 18 must preserve its deferred output and produce one runtime-hook violation.

### Итерация 19

Обход: an import boundary installs a temporary `set_error_handler()` and silently consumes partner warnings.
Почему SODA не поймала: the first runtime-hook formulation covered shutdown lifecycle only, not mutation of PHP's process error policy.
Новое/изменённое правило: `no_runtime_hooks` also rejects `set_error_handler()`.
Изменения в тестах: snapshot 19 must import successfully, pass every unrelated rule, and produce one runtime-hook violation.

### Итерация 20

Обход: a tenant policy compiler generates and evaluates PHP, presenting `eval` as a performance optimization.
Почему SODA не поймала: no existing dynamic-dispatch rule treated runtime source compilation as hidden executable behavior.
Новое/изменённое правило: `no_runtime_code_evaluation` rejects `eval` expressions.
Изменения в тестах: snapshot 20 must execute the generated policy before hardening and yield exactly one evaluation finding afterward.

### Итерация 21

Обход: deployment bootstrap writes configuration with `putenv()` but never reads it locally.
Почему SODA не поймала: environment policy covered reads only, despite writes creating a more dangerous process-wide side effect.
Новое/изменённое правило: `no_environment_reads` now rejects `putenv()` as environment access.
Изменения в тестах: snapshot 21 passed the previous catalog and now produces one environment violation.

### Итерация 22

Обход: a precision policy changes PHP configuration through `ini_set()`.
Почему SODA не поймала: environment checks did not cover other process configuration stores.
Новое/изменённое правило: `no_process_configuration_mutation` rejects runtime ini mutation.
Изменения в тестах: snapshot 22 remains executable and produces one process-configuration finding.

### Итерация 23

Обход: a storage boundary calls `chdir()` so unrelated adapters silently inherit its root.
Почему SODA не поймала: the first process rule recognized ini mutation but not working-directory state.
Новое/изменённое правило: `no_process_configuration_mutation` also rejects `chdir()`.
Изменения в тестах: snapshot 23 passes unrelated checks and exposes exactly one cwd mutation.

### Итерация 24

Обход: monetary formatting installs a worker-wide locale through `setlocale()`.
Почему SODA не поймала: locale is process state expressed through a distinct named API.
Новое/изменённое правило: `no_process_configuration_mutation` also rejects `setlocale()`.
Изменения в тестах: snapshot 24 verifies working output and one locale mutation.

### Итерация 25

Обход: reporting code installs the tenant timezone globally instead of accepting a clock/context.
Почему SODA не поймала: timezone mutation was outside the earlier ini/cwd/locale API list.
Новое/изменённое правило: `no_process_configuration_mutation` also rejects `date_default_timezone_set()`.
Изменения в тестах: snapshot 25 passed the former catalog and now produces one finding.

### Итерация 26

Обход: a responder emits `Cache-Control` directly while pretending to return a plain string.
Почему SODA не поймала: no rule modeled PHP response globals as hidden output.
Новое/изменённое правило: `no_direct_response_globals` rejects direct `header()` calls.
Изменения в тестах: snapshot 26 keeps rendering and now exposes one response side effect.

### Итерация 27

Обход: tenant affinity is persisted with `setcookie()` inside a manager method.
Почему SODA не поймала: the first response-global formulation covered headers but not cookie APIs.
Новое/изменённое правило: `no_direct_response_globals` also rejects cookie emission.
Изменения в тестах: snapshot 27 passes unrelated rules and produces one cookie finding.

### Итерация 28

Обход: checkout opens session state as an implementation detail.
Почему SODA не поймала: session lifecycle is hidden global response/input state behind another API.
Новое/изменённое правило: `no_direct_response_globals` also rejects `session_start()`.
Изменения в тестах: snapshot 28 remains working and reports exactly one session boundary leak.

### Итерация 29

Обход: a context hydrator uses `extract()` to manufacture locally convenient variables.
Почему SODA не поймала: explicit global and dynamic-call rules did not model mutation of the current symbol table.
Новое/изменённое правило: `no_symbol_table_mutation` rejects `extract()`.
Изменения в тестах: snapshot 29 proves the manufactured tenant variable worked before the new finding.

### Итерация 30

Обход: a compatibility gateway declares no inputs and recovers them with `func_get_args()`.
Почему SODA не поймала: `max_arguments` counted declared parameters, so an empty signature looked ideal.
Новое/изменённое правило: `no_implicit_arguments` rejects `func_get_args()`, `func_get_arg()`, and `func_num_args()`.
Изменения в тестах: snapshot 30 keeps accepting its hidden command and now yields one implicit-contract violation.

### Итерация 31

Обход: a checkpoint repository writes directly through `file_put_contents()` and calls the stream universal infrastructure.
Почему SODA не поймала: storage effects were not represented by any architecture rule.
Новое/изменённое правило: `no_direct_filesystem_mutation` initially rejects direct writes.
Изменения в тестах: snapshot 31 keeps returning its receipt and now yields one storage finding.

### Итерация 32

Обход: an export manager deletes handed-off files with `unlink()`.
Почему SODA не поймала: the first filesystem formulation covered persistence but not destructive cleanup.
Новое/изменённое правило: `no_direct_filesystem_mutation` also rejects deletes.
Изменения в тестах: snapshot 32 creates and releases a real temporary export, then reports one violation.

### Итерация 33

Обход: an atomic publisher performs infrastructure state transition through `rename()`.
Почему SODA не поймала: moving a file used a third mutation API outside write/delete detection.
Новое/изменённое правило: `no_direct_filesystem_mutation` also rejects renames.
Изменения в тестах: snapshot 33 executes the move and yields exactly one filesystem finding.

### Итерация 34

Обход: tenant storage is provisioned lazily with `mkdir()` inside application behavior.
Почему SODA не поймала: directory provisioning was absent from the file-oriented mutation list.
Новое/изменённое правило: `no_direct_filesystem_mutation` also rejects directory creation.
Изменения в тестах: snapshot 34 provisions a process-specific temp directory and reports one mutation.

### Итерация 35

Обход: a formatting adapter delegates to `shell_exec()` and returns a clean scalar.
Почему SODA не поймала: explicit named shell APIs were neither dynamic invocation nor hidden dependencies in existing metrics.
Новое/изменённое правило: `no_subprocess_execution` initially rejects `shell_exec()`.
Изменения в тестах: snapshot 35 proves the native formatter worked before the new finding.

### Итерация 36

Обход: a compatibility gateway uses `exec()` and normalizes its line buffer.
Почему SODA не поймала: the initial subprocess check recognized only the expression-returning shell helper.
Новое/изменённое правило: `no_subprocess_execution` also rejects `exec()`.
Изменения в тестах: snapshot 36 passes unrelated rules and produces one subprocess violation.

### Итерация 37

Обход: a runner streams output with `system()` to avoid application buffering.
Почему SODA не поймала: streaming used another named PHP process API.
Новое/изменённое правило: `no_subprocess_execution` also rejects `system()`.
Изменения в тестах: snapshot 37 retains visible output and produces one finding.

### Итерация 38

Обход: `popen()` is wrapped as a lightweight pipe transport.
Почему SODA не поймала: a stream resource looked like ordinary IO rather than subprocess execution.
Новое/изменённое правило: `no_subprocess_execution` also rejects `popen()`.
Изменения в тестах: snapshot 38 reads and closes the pipe successfully, then reports one violation.

### Итерация 39

Обход: a broker codec trusts an internal native serialized envelope.
Почему SODA не поймала: object construction hidden in `unserialize()` was outside reflection and dynamic-code checks.
Новое/изменённое правило: `no_native_deserialization` rejects `unserialize()`.
Изменения в тестах: snapshot 39 decodes its envelope and now yields exactly one finding.

### Итерация 40

Обход: a convention loader includes executable policy from a colocated non-PHP-suffixed file.
Почему SODA не поймала: the scanner ignored the included file and no rule inspected include expressions in the caller.
Новое/изменённое правило: `no_runtime_includes` rejects include/require expressions regardless of target suffix.
Изменения в тестах: snapshot 40 executes the included policy and produces one runtime-include violation.

### Итерация 41

Обход: a canonical billing service preserves a legacy API through `class_alias()`.
Почему SODA не поймала: the alias changes the runtime type graph without dynamic calls or extra declared classes.
Новое/изменённое правило: `no_runtime_type_aliases` rejects `class_alias()`.
Изменения в тестах: snapshot 41 remains usable and now produces one alias finding.

### Итерация 42

Обход: a partner record opts into `AllowDynamicProperties` and calls the resulting schema flexible.
Почему SODA не поймала: magic-method checks did not cover PHP's attribute-based dynamic property escape hatch.
Новое/изменённое правило: `no_dynamic_properties` rejects the opt-in attribute.
Изменения в тестах: snapshot 42 hydrates the partner reference and yields one shape violation.

### Итерация 43

Обход: a local strategy is hidden in an anonymous class so project/type limits do not expose it.
Почему SODA не поймала: named-class metrics intentionally ignored anonymous class boundaries.
Новое/изменённое правило: `no_anonymous_classes` requires responsibilities to have named types.
Изменения в тестах: snapshot 43 executes its strategy and produces one anonymous-class finding.

### Итерация 44

Обход: a convention factory constructs `new $class` while all later method calls remain statically named.
Почему SODA не поймала: `no_dynamic_invocation` covered calls but not dynamic constructor targets.
Новое/изменённое правило: `no_dynamic_invocation` also rejects dynamic construction.
Изменения в тестах: snapshot 44 constructs `stdClass` and now yields one dynamic invocation.

### Итерация 45

Обход: prototype cloning is marketed as immutable forking without exposing construction.
Почему SODA не поймала: `clone` is neither reflective construction nor a normal call.
Новое/изменённое правило: `no_object_cloning` requires explicit copy semantics.
Изменения в тестах: snapshot 45 returns a valid copied object and produces one clone finding.

### Итерация 46

Обход: a policy adapter uses `Closure::bind()` to rewrite execution scope.
Почему SODA не поймала: the named static call looked ordinary despite bypassing ownership.
Новое/изменённое правило: `no_closure_rebinding` rejects static rebinding.
Изменения в тестах: snapshot 46 confirms a bound closure and yields one finding.

### Итерация 47

Обход: the same scope escape is expressed through instance `bindTo()` and called portability.
Почему SODA не поймала: the initial formulation covered only the static Closure API.
Новое/изменённое правило: `no_closure_rebinding` also rejects `bindTo()`.
Изменения в тестах: snapshot 47 passes unrelated rules and produces one rebinding violation.

### Итерация 48

Обход: a gateway negotiator discovers optional behavior with `method_exists()`.
Почему SODA не поймала: runtime capability discovery is a named predicate and satisfies control-condition limits.
Новое/изменённое правило: `no_runtime_contract_probes` initially rejects method probing.
Изменения в тестах: snapshot 48 negotiates successfully and produces one probe finding.

### Итерация 49

Обход: a payload reader replaces method probing with `property_exists()`.
Почему SODA не поймала: object shape used a separate introspection API.
Новое/изменённое правило: `no_runtime_contract_probes` also rejects property probing.
Изменения в тестах: snapshot 49 returns its compatibility outcome and yields one finding.

### Итерация 50

Обход: a handler negotiator validates string callbacks with `is_callable()` without invoking them.
Почему SODA не поймала: dynamic invocation never occurs inside the inspected code.
Новое/изменённое правило: `no_runtime_contract_probes` also rejects callable probing.
Изменения в тестах: snapshot 50 remains working and reports one hidden-contract probe.

Every row has an executable snapshot in `iterations/`. Before rule N is added, snapshot N passes the complete 61-rule catalog with that one rule removed; after it is added, the complete catalog produces exactly one violation for that snapshot. Snapshot N also passes the architecture rules introduced in rows 1 through N−1. This makes the progression adversarial instead of presenting ten unrelated findings after the fact.

For every iteration, `AdversarialDemoTest` proves all six stages: the bad code executes, all other catalog rules miss it, the documented analysis explains the gap, a new general rule detects it exactly once, the regression assertion fixes that behavior, and the numbered snapshot records the result. The final `run.php` is an integration fixture containing all ten escapes at once.
