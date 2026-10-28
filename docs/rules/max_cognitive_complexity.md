# max_cognitive_complexity

📝 Caps Soda's PHP cognitive complexity score per method, reporting every contributing line as evidence.

💼 This rule is enabled in the ✅ `standard` config.

## Why

Nested decisions are harder to follow than the same decisions in a named step.
This rule reports methods above the configured score and identifies the source
lines that contribute to it.

Exceeding the limit produces a quality violation and can fail CI. The metric's
research origin does not make it a nongating research check.

## Examples

```php
// ❌ run has cognitive complexity 16
final class BatchRunner
{
    public function run(array $items): void
    {
        for ($i = 0; $i < 3; $i++) {
            foreach ($items as $item) {
                if ($item->ready() && $item->valid()) {
                    if ($item->priority()) {
                        if ($item->fresh()) {
                            $item->process();
                        }
                    }
                }
            }
        }
    }
}
```

The named step below preserves all three passes, every eligibility condition,
and their short-circuit evaluation. An eligible item is processed once per pass.

```php
// ✅ run has cognitive complexity 3; processEligible has complexity 4
final class BatchRunner
{
    public function run(array $items): void
    {
        for ($i = 0; $i < 3; $i++) {
            foreach ($items as $item) {
                $this->processEligible($item);
            }
        }
    }

    private function processEligible(Item $item): void
    {
        if (! $item->ready() || ! $item->valid()) {
            return;
        }

        if (! $item->priority() || ! $item->fresh()) {
            return;
        }

        $item->process();
    }
}
```

## Scope and exceptions

Soda's PHP cognitive metric counts nesting, boolean operator runs, jumps, and
recursion. Every contribution appears with its line and reason in the diagnostic.
See [the metric definition](../RESEARCH_METRICS.md#soda-php-cognitive-v1) for the
counting rules and limits of this measurement.

Review the reported control flow before changing the code. Extract a named step
when it clarifies the operation; do not introduce objects, traits, or value
objects solely to lower the score. Unlike
[representation_churn](representation_churn.md), this rule produces violations.

## Options

- `limit` (default `15`): maximum accepted cognitive complexity per method, including anonymous scopes. The value must be non-negative; `0` permits only linear code. Each point of the score is attributed to a source line and reason in the diagnostic message.

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Complexity\MaxCognitiveComplexity;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new MaxCognitiveComplexity(15),
    ]);
```

## Related rules

- [max_cyclomatic_complexity](max_cyclomatic_complexity.md) — a structural branch count that ignores nesting, complementary to this nesting-aware score.
- [max_control_nesting](max_control_nesting.md) — bounds nesting depth directly, the main driver of cognitive complexity.
