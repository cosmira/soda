<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\SingleVisitorTraversal;
use Cosmira\Soda\Config\FnmatchPathExclusion;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;

/**
 * Discourages “structured array” usage. Default {@see ListOnlyArrayStrictness::Pragmatic}: chained `[$a][$b]` only.
 * Use {@see ListOnlyArrayStrictness::Strict} for string keys + `array{...}` PHPDoc as well.
 *
 * Method/function `array` parameters and return types are not checked — usage in the body matters.
 *
 * Path skipping uses {@see FnmatchPathExclusion}: Laravel-style `*Request.php`, plus optional patterns/prefixes for boundary layers.
 *
 * @example soda.php: `new OnlyListArraysAllowed()`, strict: `new OnlyListArraysAllowed(strictness: ListOnlyArrayStrictness::Strict)`
 */
final class OnlyListArraysAllowed extends Check
{
    /**
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Defines message pragmatic used by this policy.
     */
    private const string MESSAGE_PRAGMATIC = 'Nested array indexing is forbidden. Prefer objects, a flat list, or split access into steps instead of chaining `[]`.';

    /**
     * Defines message strict used by this policy.
     */
    private const string MESSAGE_STRICT = 'Only list arrays are allowed. Associative arrays are forbidden. Use Value Objects instead.';

    /**
     * Basename / path fnmatch patterns (merged with the constructor patterns).
     *
     * @var list<string>
     */
    private const array DEFAULT_IGNORE_PATH_PATTERNS = [
        '*Request.php',
        '*FormRequest.php',
        '*Resource.php',
        '*JsonResource.php',
    ];

    /**
     * Stores path exclusion for this analysis instance.
     */
    private readonly FnmatchPathExclusion $pathExclusion;

    /**
     * @param string[] $ignorePathPatterns Extra fnmatch patterns against the full file path or basename.
     * @param string[] $ignorePathPrefixes Normalized path prefixes: file is skipped if its resolved path is under one of these.
     */
    public function __construct(
        array $ignorePathPatterns = [],
        array $ignorePathPrefixes = [],
        private readonly ListOnlyArrayStrictness $strictness = ListOnlyArrayStrictness::Pragmatic,
    ) {
        $patterns = array_values(array_unique([
            ...self::DEFAULT_IGNORE_PATH_PATTERNS,
            ...$ignorePathPatterns,
        ]));
        $prefixes = array_values(array_unique($ignorePathPrefixes));
        $this->pathExclusion = new FnmatchPathExclusion($patterns, $prefixes);
    }

    /**
     * Return the stable rule identifier used in configuration and reports.
     */
    #[\Override]
    public function id(): string
    {
        return 'only_list_arrays';
    }

    /**
     * Evaluate this rule using the already collected file facts.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $facts): iterable
    {
        if ($this->pathExclusion->isExcludedPath($facts->path)) {
            return;
        }

        $visitor = new ListOnlyArrayCollectingVisitor($this->strictness);
        SingleVisitorTraversal::traverse($facts->nodes, $visitor);
        $message = $this->strictness === ListOnlyArrayStrictness::Strict
            ? self::MESSAGE_STRICT
            : self::MESSAGE_PRAGMATIC;

        foreach ($visitor->lines() as $line) {
            yield new Violation(rule: $this->id(), file: $facts->path, value: 1, threshold: 0, line: $line, message: $message);
        }
    }
}
