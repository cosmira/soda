<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Documentation;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;

final class MultilineMethodPhpDoc extends Check
{
    /**
     * @param list<'public'|'protected'|'private'> $visibilities
     */
    public function __construct(private readonly array $visibilities = ['public']) {}

    /**
     * Inspect declarations in the already parsed syntax tree.
     *
     * @return list<string>
     */
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Produce diagnostics directly from the declaration that needs documentation.
     *
     * @return iterable<Violation>
     */
    public function checkFile(FileFacts $file): iterable
    {
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, ClassLike::class) as $class) {
            foreach ($class->getMethods() as $member) {
                yield from $this->violations($file, $class, $member);
            }
        }
    }

    /**
     * Report each declared name, preserving shared property and constant locations.
     *
     * @return iterable<Violation>
     */
    private function violations(FileFacts $file, ClassLike $class, ClassMethod $member): iterable
    {
        $visibility = $this->visibility($member);
        $isSelected = in_array($visibility, $this->visibilities, true);
        $isDocumented = (new MultilinePhpDocComment)->isValid($member);
        $shouldSkip = ! $isSelected || $isDocumented;
        if ($shouldSkip) {
            return;
        }

        $name = $member->name->toString();
        yield new Violation(
            rule: $this->id(),
            file: $file->path,
            value: 0,
            threshold: 1,
            method: $name,
            class: $class->name?->toString() ?? 'anonymous@'.$class->getLine(),
            line: $member->getLine(),
            message: sprintf('%s method::%s must have a multiline PHPDoc comment', ucfirst($visibility), $name),
        );
    }

    /**
     * Return the explicit visibility shared by methods, properties and constants.
     */
    private function visibility(ClassMethod $member): string
    {
        if ($member->isProtected()) {
            return 'protected';
        }

        return $member->isPrivate() ? 'private' : 'public';
    }

    /**
     * Identify this rule in reports.
     */
    public function id(): string
    {
        return 'multiline_method_phpdoc';
    }
}
