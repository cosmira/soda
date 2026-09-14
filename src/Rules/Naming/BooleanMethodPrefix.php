<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Naming;

use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;

final class BooleanMethodPrefix extends Check
{
    /**
     * Defines default ignore used by this policy.
     */
    private const array DEFAULT_IGNORE = [
        'create', 'update', 'delete', 'execute', 'run', 'process',
        'handle', 'dispatch', 'send', 'save', 'store', 'generate', 'build', 'check',
    ];

    /**
     * Defines default prefix used by this policy.
     */
    private const array DEFAULT_PREFIX = [
        'is', 'has', 'can', 'should', 'does', 'was',
        'are', 'do', 'did', 'will', 'try',
    ];

    /**
     * Stores ignore for this analysis instance.
     */
    private array $ignore;

    /**
     * Stores prefix for this analysis instance.
     */
    private array $prefix;

    /**
     * @param string[] $ignore Method names to skip.
     * @param string[] $prefix Allowed prefixes (defaults cover the most common ones).
     */
    public function __construct(
        array $ignore = [],
        array $prefix = [],
    ) {
        $this->ignore = array_merge(self::DEFAULT_IGNORE, $ignore);
        $this->prefix = array_merge(self::DEFAULT_PREFIX, $prefix);
    }

    /**
     * Preserve the standard bundle's original vocabulary and empty ignore list.
     */
    public static function standard(): self
    {
        $rule = new self;
        $rule->ignore = [];
        $rule->prefix = ['is', 'has', 'should', 'can', 'try'];

        return $rule;
    }

    /**
     * Identify this rule in reports.
     */
    public function id(): string
    {
        return 'boolean_methods_without_prefix';
    }

    /**
     * Use the already parsed syntax tree.
     *
     * @return list<string>
     */
    public function requiredAnalyses(): array
    {
        return ['naming'];
    }

    /**
     * Check boolean-returning methods after inherited contracts are known.
     *
     * @return iterable<Violation>
     */
    public function checkProject(ProjectFacts $project): iterable
    {
        $inspector = new BooleanMethodPrefixInspector($project, $this->ignore, $this->prefix);
        foreach ($project->files as $file => $metrics) {
            foreach ($inspector->violations($metrics) as $index => $method) {
                yield new Violation(
                    rule: $this->id(), file: $file, value: $index + 1, threshold: 0, method: $method['name'] ?? null, class: $method['class'] ?? null, line: $method['line'] ?? null,
                    message: sprintf('Boolean-returning method %s() should start with %s', $method['methodName'] ?? 'method', implode('/', $this->prefix)),
                );
            }
        }
    }
}
