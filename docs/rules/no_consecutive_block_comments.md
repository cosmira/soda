# no_consecutive_block_comments

📝 Reports adjacent block comments that should be merged into a single comment with the description and PHPDoc tags together.

💼 This rule is enabled in the ✅ `standard` config.

## Why

Splitting a declaration's documentation into adjacent blocks can hide its description from tools that read only the last comment. It also scatters one explanation across two places. The rule reports a block comment (`/** ... */` or `/* ... */`) that follows another block comment, separated only by whitespace. Line comments (`//`, `#`) are outside this policy. Any code, attribute, or `?>` between the blocks breaks the sequence.

An ordinary block containing only a directive, such as `/* @section */` or
`/* @end-section */`, also breaks the sequence. Source tools can require these
markers to remain separate comments. PHPDoc tags such as `/** @internal */`
still belong with the declaration's description and other tags.

## Examples

```php
// ❌
/**
 * Override the protected methods below to customize entity names
 * and ignored attributes for a model.
 */

/**
 * @var array<string, int>
 */
private array $changes = [];

// ✅
/**
 * Override the protected methods below to customize entity names
 * and ignored attributes for a model.
 *
 * @var array<string, int>
 */
private array $changes = [];
```

```php
// ❌ Even ordinary block comments stack up.
/* Setup phase */
/* Tune the buffer before the run */
$buffer = 128;

// ✅ One block, one explanation.
/* Tune the buffer before the run */
$buffer = 128;
```

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Documentation\NoConsecutiveBlockComments;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new NoConsecutiveBlockComments(),
    ]);
```

## Related rules

- [multiline_method_phpdoc](multiline_method_phpdoc.md) — requires the merged block to contain a description before any tags.
- [multiline_property_phpdoc](multiline_property_phpdoc.md) — the same documentation requirement for properties.
