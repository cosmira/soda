# no_assignment_in_condition

📝 Forbids assignments inside control-flow conditions.

💼 This rule is enabled in the ✅ `standard` config.

## Why

An assignment inside a condition hides a state change inside a question. Readers can mistake `=` for `==`; evaluating the condition also mutates the program. The rule reports assignments, including compound and reference assignments, in `if`, `elseif`, `while`, `do-while`, `for` conditions, and ternary conditions. Assign first, then make the condition a pure question. Assignments in a `for` statement's initialization and loop expressions are accepted. Assignments inside nested callable bodies also remain outside this rule.

## Examples

```php
// ❌ hidden state change in the condition
if ($value = $next()) {
    $consume($value);
}

// ✅ assign first, then test
$value = $next();
if ($value) {
    $consume($value);
}
```

```php
// ❌ compound assignment inside a loop condition
while ($offset += $step()) {
    $consume($offset);
}

// ✅ the for loop expressions are the accepted place for it
for ($offset += $step(); $offset; $offset += $step()) {
    $consume($offset);
}
```

The first rewrite preserves PHP truthiness, including `false`, `0`, `'0'`, `''`
and `[]`; a null comparison would change behavior. The loop preserves the initial
offset and performs the same update before each condition, including after a
`continue`. Do not hoist a loop read into a one-time assignment.

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Complexity\NoAssignmentInCondition;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new NoAssignmentInCondition,
    ]);
```

## Related rules

- [no_assignment_in_return](no_assignment_in_return.md) — the same hidden-write concern applied to return expressions.
- [no_chained_assignments](no_chained_assignments.md) — targets `a = b = value` chains that also bury writes inside expressions.
- [no_complex_control_conditions](no_complex_control_conditions.md) — keeps conditions readable once the assignments are gone.
