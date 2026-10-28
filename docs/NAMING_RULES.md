# Naming policies

Choose naming rules for the reading problem you want to solve. Short names can
hide meaning; long names can repeat information already present in a type or
scope. A numerical limit alone cannot choose the right domain word.

- [Repeated context](#avoid_redundant_naming) checks names against nearby types.
- [Predicates](#boolean_methods_without_prefix) checks question-style boolean names.
- [Generic role names](#no_dumpster_suffixes) checks names such as `OrderHelper`.
- [Name lengths](#name-length-rules) checks mechanical length limits.
- [Compound local names](#max_variable_name_words) is a separate optional policy.

Individual rule pages are the primary reference for examples, options, and
analysis boundaries. See the [rule index](rules/README.md) for default selection
and quality-result effects, or [Configuration](SODA_PHP_CONFIG.md) to choose rules.
The topics here are reading categories; API section names come from `list:rules`.

## avoid_redundant_naming

Detects class and method names that repeat words already supplied by the class or namespace, and suggests the shorter name. See [examples and options](rules/avoid_redundant_naming.md).

## boolean_methods_without_prefix

Requires boolean-returning methods to read as questions by starting with an explicit predicate prefix such as `is`, `has`, or `should`. See [examples and options](rules/boolean_methods_without_prefix.md).

## no_dumpster_suffixes

Rejects type names ending in generic role suffixes instead of naming what the type does. See [examples and options](rules/no_dumpster_suffixes.md).

## Name length rules

Use length limits to flag names worth reviewing. Preserve framework contracts,
units, and domain distinctions when shortening an identifier. Each rule page
specifies its own exclusions; do not assume that every short hook is exempt.

## variable_name_length

Keeps variable names within inclusive length bounds to avoid both cryptic abbreviations and sentence-like identifiers. See [examples and options](rules/variable_name_length.md).

## method_name_length

Keeps declared method names within inclusive length bounds so each method names one clear action. See [examples and options](rules/method_name_length.md).

## class_name_length

Keeps declared class, interface, trait, and enum names within inclusive length bounds. See [examples and options](rules/class_name_length.md).

## namespace_name_length

Keeps each namespace segment within inclusive length bounds so namespaces stay short domain terms. See [examples and options](rules/namespace_name_length.md).

## max_variable_name_words

This optional rule limits words in local variable and parameter names. It is
configured directly and does not appear in the built-in catalog. See
[examples, options, and scope](rules/max_variable_name_words.md).

## Adopting a naming policy

Start with a finding in its actual scope. If a name repeats a declared type,
remove that repetition without losing the distinction the caller needs. If a
name carries a unit or domain qualifier, establish that the shorter name still
communicates it before changing the code.

Optional naming checks express a team preference. Their presence in the catalog
does not mean that the standard bundle enables them. Review the index before
adding a new policy to an existing project.
