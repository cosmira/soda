<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Naming;

use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;

#[Rule(
    label: 'Boolean method prefixes:',
    advice: 'Name the method as a question with is, has, can, should, or another explicit predicate prefix.',
)]
final class BooleanMethodPrefix extends Check
{
    /**
     * Operation verbs that may legitimately return a success flag.
     */
    private const DEFAULT_IGNORE = [
        'create', 'update', 'delete', 'execute', 'run', 'process',
        'handle', 'dispatch', 'send', 'save', 'store', 'generate', 'build', 'check',
    ];

    /**
     * Predicate prefixes that make a boolean method read as a question.
     */
    private const DEFAULT_PREFIX = [
        'is', 'has', 'can', 'should', 'does', 'was',
        'are', 'do', 'did', 'will', 'try',
    ];

    /**
     * Method names and Class::method references never flagged.
     */
    private readonly array $ignore;

    /**
     * Allowed predicate prefixes, merged over the built-in vocabulary.
     */
    private readonly array $prefix;

    /**
     * Configure ignored boolean methods and extend the allowed predicate prefixes.
     *
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
