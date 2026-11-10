<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Architecture;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Violation;
use InvalidArgumentException;

/**
 * Rejects configured directions between namespace modules for class and trait dependencies.
 */
final class ForbiddenDependencies extends Check
{
    /**
     * Configure source namespace prefixes and the target prefixes they cannot depend on.
     *
     * @param array<string, list<string>> $forbidden Empty mappings impose no boundaries.
     */
    public function __construct(
        /**
         * Directed namespace restrictions, matched case-insensitively at namespace boundaries.
         */
        private readonly array $forbidden = [],
    ) {
        foreach ($forbidden as $source => $targets) {
            $this->assertPrefix($source);
            foreach ($targets as $target) {
                $this->assertPrefix($target);
            }
        }
    }

    /**
     * Reject empty namespace prefixes rather than silently applying a global restriction.
     */
    private function assertPrefix(string $prefix): void
    {
        throw_if(trim($prefix, '\\') === '', InvalidArgumentException::class, 'Dependency namespace prefixes must not be empty.');
    }

    /**
     * Identify the directed dependency policy in reports and configuration.
     */
    public function id(): string
    {
        return 'forbidden_dependencies';
    }

    /**
     * Reuse resolved type references without collecting unrelated analyses.
     *
     * @return list<string>
     */
    public function requiredAnalyses(): array
    {
        return ['structure', 'coupling'];
    }

    /**
     * Report each prohibited source-to-target edge once at the owning declaration.
     */
    public function checkFile(FileFacts $file): iterable
    {
        foreach ($file->metrics['classes'] as $name => $class) {
            foreach ($class['depends_on'] ?? [] as $dependency) {
                if (! $this->isForbidden($name, $dependency)) {
                    continue;
                }

                yield new Violation(
                    rule: $this->id(), file: $file->path, value: 1, threshold: 0,
                    class: $name, line: $class['line'],
                    message: sprintf('Forbidden dependency: %s -> %s. Move the dependency across the configured module boundary.', $name, $dependency),
                );
            }
        }
    }

    /**
     * Treat overlapping restrictions as a union without duplicating diagnostics.
     */
    private function isForbidden(string $source, string $target): bool
    {
        foreach ($this->forbidden as $prefix => $targets) {
            if (! $this->isInNamespace($source, $prefix)) {
                continue;
            }

            foreach ($targets as $targetPrefix) {
                if ($this->isInNamespace($target, $targetPrefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Match exact types and namespace descendants, excluding similarly named siblings.
     */
    private function isInNamespace(string $type, string $prefix): bool
    {
        $type = strtolower(ltrim($type, '\\'));
        $prefix = strtolower(trim($prefix, '\\'));

        return $type === $prefix || str_starts_with($type, $prefix.'\\');
    }
}
