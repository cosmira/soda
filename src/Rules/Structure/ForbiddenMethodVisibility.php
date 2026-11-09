<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\CallableIdentity;
use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Support\FnmatchPathExclusion;
use InvalidArgumentException;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

final class ForbiddenMethodVisibility extends Check
{
    /**
     * Directory boundaries and file masks use the same semantics as per-rule exclusions.
     */
    private readonly FnmatchPathExclusion $pathMatcher;

    /**
     * Select files or fully qualified classes; leaving both selections empty checks every class.
     *
     * @param list<non-empty-string>               $paths        File paths, directory boundaries, or glob masks.
     * @param list<non-empty-string>               $classes      Fully qualified class names or glob masks.
     * @param list<'public'|'protected'|'private'> $visibilities
     */
    public function __construct(
        private readonly array $paths = [],
        private readonly array $classes = [],
        private readonly array $visibilities = ['private', 'protected'],
    ) {
        foreach ([...$paths, ...$classes] as $target) {
            throw_if(! is_string($target) || trim($target) === '', InvalidArgumentException::class, 'Method restriction targets must be non-empty strings.');
        }

        foreach ($visibilities as $visibility) {
            throw_if(! in_array($visibility, ['public', 'protected', 'private'], true), InvalidArgumentException::class, 'Method restrictions accept only public, protected or private visibility.');
        }

        $this->pathMatcher = new FnmatchPathExclusion($paths, $paths, flags: 0);
    }

    /**
     * Identify the opt-in visibility policy in diagnostics.
     */
    public function id(): string
    {
        return 'forbidden_method_visibility';
    }

    /**
     * Use resolved declarations without collecting additional metrics.
     *
     * @return list<string>
     */
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Inspect only directly declared methods, keeping nested classes in their own scope.
     *
     * @return iterable<Violation>
     */
    public function checkFile(FileFacts $file): iterable
    {
        $matchesPath = ($this->paths === [] && $this->classes === []) || $this->pathMatcher->isExcludedPath($file->path);
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, Class_::class) as $class) {
            $name = CallableIdentity::type($class);
            if (! $matchesPath && ! $this->isSelectedClass($name)) {
                continue;
            }

            foreach ($class->getMethods() as $method) {
                $visibility = $this->visibility($method);
                if (! in_array($visibility, $this->visibilities, true)) {
                    continue;
                }

                yield new Violation(
                    rule: $this->id(), file: $file->path, value: 1, threshold: 0,
                    method: $method->name->toString(), class: $name, line: $method->getStartLine(),
                    message: sprintf('Selected class %s must not declare %s method %s().', $name, $visibility, $method->name),
                );
            }
        }
    }

    /**
     * PHP type names are case-insensitive; namespace separators must remain literal in masks.
     */
    private function isSelectedClass(string $name): bool
    {
        foreach ($this->classes as $target) {
            if (fnmatch(strtolower(ltrim($target, '\\')), strtolower($name), FNM_NOESCAPE)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Methods without an explicit modifier are public in PHP.
     */
    private function visibility(ClassMethod $method): string
    {
        return match (true) {
            $method->isPrivate()   => 'private',
            $method->isProtected() => 'protected',
            default                => 'public',
        };
    }
}
