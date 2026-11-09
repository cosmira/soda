<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Support\FnmatchPathExclusion;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;

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
     * Use the parsed syntax tree directly without requesting additional metric collectors.
     *
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Diagnostic text asking the author to review the shape of the data.
     */
    private const MESSAGE_PRAGMATIC = 'This policy forbids nested array indexing. Review the data shape: repeated domain meaning or invariants can justify a value object; introducing a temporary alone does not improve the model.';

    /**
     * Diagnostic text used when strict mode forbids every associative array.
     */
    private const MESSAGE_STRICT = 'Only list arrays are allowed. Associative arrays are forbidden. Use Value Objects instead.';

    /**
     * Basename / path fnmatch patterns (merged with the constructor patterns).
     *
     * @var list<string>
     */
    private const DEFAULT_IGNORE_PATH_PATTERNS = [
        '*Request.php',
        '*FormRequest.php',
        '*Resource.php',
        '*JsonResource.php',
    ];

    /**
     * Matches source paths exempt from the list-array policy.
     */
    private readonly FnmatchPathExclusion $pathExclusion;

    /**
     * Configure nested array-access strictness and excluded source paths.
     *
     * @param string[]                $ignorePathPatterns Extra fnmatch patterns against the full file path or basename.
     * @param string[]                $ignorePathPrefixes Normalized path prefixes: file is skipped if its resolved path is under one of these.
     * @param ListOnlyArrayStrictness $strictness         Selection policy for nested array access.
     */
    public function __construct(
        array $ignorePathPatterns = [],
        array $ignorePathPrefixes = [],
        /**
         * Policy deciding which nested array accesses should be reported.
         */
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
        (new NodeTraverser(new ParentConnectingVisitor(), $visitor))->traverse($facts->nodes);
        $message = $this->strictness === ListOnlyArrayStrictness::Strict
            ? self::MESSAGE_STRICT
            : self::MESSAGE_PRAGMATIC;

        foreach ($visitor->lines() as $line) {
            yield new Violation(rule: $this->id(), file: $facts->path, value: 1, threshold: 0, line: $line, message: $message);
        }
    }
}
