<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Documentation;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;

final class MultilinePropertyPhpDoc extends Check
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
            foreach (($class instanceof Interface_ ? [] : $class->getProperties()) as $member) {
                yield from $this->violations($file, $class, $member);
            }

            $constructor = $class->getMethod('__construct');
            if ($constructor !== null) {
                yield from $this->promotedViolations($file, $class, $constructor);
            }
        }
    }

    /**
     * Treat promoted parameters as property declarations with their own visibility.
     *
     * @return iterable<Violation>
     */
    private function promotedViolations(FileFacts $file, ClassLike $class, ClassMethod $constructor): iterable
    {
        foreach ($constructor->params as $parameter) {
            if (! $parameter->isPromoted() || ! $parameter->var instanceof Variable) {
                continue;
            }

            $visibility = $this->visibility($parameter);
            $selected = in_array($visibility, $this->visibilities, true);
            $documented = $this->isPromotedDocumented($parameter, $constructor);
            if (! $selected || $documented) {
                continue;
            }

            yield new Violation(
                rule: $this->id(), file: $file->path, value: 0, threshold: 1,
                class: $class->name?->toString() ?? 'anonymous@'.$class->getLine(),
                line: $parameter->getLine(),
                message: sprintf('%s property::%s must have a multiline PHPDoc comment', ucfirst($visibility), $parameter->var->name),
            );
        }
    }

    /**
     * Accept a property docblock or its exact parameter tag on the constructor.
     */
    private function isPromotedDocumented(Param $parameter, ClassMethod $constructor): bool
    {
        $comments = new MultilinePhpDocComment;
        if ($comments->isValid($parameter)) {
            return true;
        }

        if (! $comments->isValid($constructor) || ! $parameter->var instanceof Variable) {
            return false;
        }

        $name = preg_quote((string) $parameter->var->name, '/');
        $pattern = '/^\s*\*\s*@param\s+[^\r\n$]+\s+&?\$'.$name.'(?=\s|$)/m';

        return preg_match($pattern, $constructor->getDocComment()?->getText() ?? '') === 1;
    }

    /**
     * Report each declared name, preserving shared property and constant locations.
     *
     * @return iterable<Violation>
     */
    private function violations(FileFacts $file, ClassLike $class, Property $member): iterable
    {
        $visibility = $this->visibility($member);
        $isSelected = in_array($visibility, $this->visibilities, true);
        $isDocumented = (new MultilinePhpDocComment)->isValid($member);
        $shouldSkip = ! $isSelected || $isDocumented;
        if ($shouldSkip) {
            return;
        }

        foreach ($member->props as $item) {
            $name = $item->name->toString();
            yield new Violation(
                rule: $this->id(),
                file: $file->path,
                value: 0,
                threshold: 1,
                class: $class->name?->toString() ?? 'anonymous@'.$class->getLine(),
                line: $member->getLine(),
                message: sprintf('%s property::%s must have a multiline PHPDoc comment', ucfirst($visibility), $name),
            );
        }
    }

    /**
     * Return the explicit visibility shared by methods, properties and constants.
     */
    private function visibility(Property|Param $member): string
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
        return 'multiline_property_phpdoc';
    }
}
