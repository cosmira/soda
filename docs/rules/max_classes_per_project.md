# max_classes_per_project

📝 Limits the total number of classes and traits declared across the analyzed project.

💼 This rule is enabled in the ✅ `standard` config.

## Why

A codebase that keeps adding types tends to fragment concepts: every variation gets its own class, and readers must hold a growing map of tiny abstractions. The rule sums the class count over all analyzed files after collection and reports the project once the total exceeds the limit. It advises looking for fragmented abstractions and merging types that do not own distinct behavior or policy.

## Examples

```php
// ❌ fragmented variations of one concept
final class OrderCreatedEmail {}
final class OrderShippedEmail {}
final class OrderCancelledEmail {}
final class OrderRefundedEmail {}
// ... one class per event, none with distinct behavior

// ✅ one owner with a policy
final class OrderEmailFactory
{
    public function forEvent(OrderEvent $event): Email { /* ... */ }
}
```

The example is illustrative: the metric is project-wide, not per file or per concept. The rule counts every class and trait declared across the analyzed files. It reports the project when the total exceeds `limit`, which defaults to `300`. Four classes do not trigger it alone, but repeated fragmentation across the codebase can.

## Options

- `limit` (default `300`): maximum accepted total number of types in the project. A non-positive limit disables the check.

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Structure\MaxClassesPerProject;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new MaxClassesPerProject(300),
    ]);
```

## Related rules

- [max_classes_per_namespace](max_classes_per_namespace.md) — applies the same fragmentation signal at namespace level.
- [max_classes_per_file](max_classes_per_file.md) — caps the number of types sharing a file.
