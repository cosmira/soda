<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\ListOnlyArray;

use Bunnivo\Soda\Config\SodaRule;
use Bunnivo\Soda\Plugins\PathExclusion\FnmatchPathExclusion;
use Bunnivo\Soda\Quality\Limits;
use Bunnivo\Soda\Quality\Report\ViolationBuilder;

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
final class OnlyListArraysAllowed extends SodaRule
{
    private const string MESSAGE_PRAGMATIC = 'Nested array indexing is forbidden. Prefer objects, a flat list, or split access into steps instead of chaining `[]`.';

    private const string MESSAGE_STRICT = 'Only list arrays are allowed. Associative arrays are forbidden. Use Value Objects instead.';

    /**
     * Basename / path fnmatch patterns (merged with {@see $ignorePathPatterns}).
     *
     * @var list<string>
     */
    private const array DEFAULT_IGNORE_PATH_PATTERNS = [
        '*Request.php',
        '*FormRequest.php',
        '*Resource.php',
        '*JsonResource.php',
    ];

    /** @var list<string> */
    private readonly array $ignorePathPatterns;

    /** @var list<string> */
    private readonly array $ignorePathPrefixes;

    private readonly FnmatchPathExclusion $pathExclusion;

    private readonly ListOnlyArrayAnalyser $listOnlyArrayAnalyser;

    /**
     * @param string[] $ignorePathPatterns Extra fnmatch patterns against the full file path or basename.
     * @param string[] $ignorePathPrefixes Normalized path prefixes: file is skipped if its resolved path is under one of these.
     */
    public function __construct(
        array $ignorePathPatterns = [],
        array $ignorePathPrefixes = [],
        private readonly ListOnlyArrayStrictness $strictness = ListOnlyArrayStrictness::Pragmatic,
    ) {
        $this->ignorePathPatterns = array_values(array_unique([
            ...self::DEFAULT_IGNORE_PATH_PATTERNS,
            ...$ignorePathPatterns,
        ]));
        $this->ignorePathPrefixes = array_values(array_unique($ignorePathPrefixes));
        $this->pathExclusion = new FnmatchPathExclusion($this->ignorePathPatterns, $this->ignorePathPrefixes);
        $this->listOnlyArrayAnalyser = new ListOnlyArrayAnalyser($this->strictness);
    }

    #[\Override]
    public function id(): string
    {
        return 'only_list_arrays';
    }

    #[\Override]
    protected function analyze(string $file): array
    {
        if ($this->pathExclusion->isExcludedPath($file)) {
            return ['only_list_arrays' => []];
        }

        return [
            'only_list_arrays' => $this->listOnlyArrayAnalyser->analyse($this->parse($file)),
        ];
    }

    /**
     * @param array<string, mixed> $metrics
     */
    #[\Override]
    protected function evaluate(string $file, array $metrics): array
    {
        $violations = [];

        $message = $this->strictness === ListOnlyArrayStrictness::Strict
            ? self::MESSAGE_STRICT
            : self::MESSAGE_PRAGMATIC;

        foreach (ListOnlyArrayFinding::fromMetricRows($metrics['only_list_arrays'] ?? []) as $finding) {
            $violations[] = ViolationBuilder::of(
                $this->id(),
                $file,
                new Limits(1, 0),
            )
                ->atLine($finding->line)
                ->withMessage($message)
                ->build();
        }

        return $violations;
    }
}
