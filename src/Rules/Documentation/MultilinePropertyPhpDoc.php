<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Documentation;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;
use PhpToken;

#[Rule(
    label: 'Multiline property PHPDoc:',
    advice: 'Describe each property in a multiline PHPDoc block before any tags; tags alone do not explain its purpose.',
    severity: 'error',
    default: 1,
    comparison: 'min',
)]
final class MultilinePropertyPhpDoc extends Check
{
    /**
     * Select the member visibilities that require written documentation.
     *
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
                message: sprintf('%s property::%s must have a multiline PHPDoc comment with a description before any tags', ucfirst($visibility), $parameter->var->name),
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

        $comment = $constructor->getDocComment()?->getText() ?? '';
        $comment = preg_replace('/^\h*\*\h?/m', '', $comment) ?? '';
        preg_match_all('/^\h*@param\h+(.+?)(?=^\h*@[a-zA-Z]|\z)/ms', $comment, $annotations);
        foreach ($annotations[1] as $annotation) {
            $hasType = preg_match('/^(?:\??[a-zA-Z_\x80-\xff\\\\]|-?[0-9]|[\'"(]|\$this\b)/', ltrim($annotation)) === 1;
            if ($hasType && $this->doesAnnotationTarget($annotation, (string) $parameter->var->name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Quoted keys and callable parameters cannot supply the promoted field's tag target.
     */
    private function doesAnnotationTarget(string $annotation, string $variable): bool
    {
        $typeSource = preg_replace('/\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"/s', "'literal'", $annotation) ?? $annotation;
        $depth = 0;
        foreach (PhpToken::tokenize('<?php '.$typeSource) as $token) {
            if ($token->id === T_VARIABLE && $depth === 0 && $token->text !== '$this') {
                return $token->text === '$'.$variable;
            }

            $depth += match ($token->text) {
                '<', '(', '[', '{'       => 1,
                '>', '>>', ')', ']', '}' => -strlen($token->text),
                default                  => 0,
            };
            if ($depth < 0) {
                return false;
            }
        }

        return false;
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
                message: sprintf('%s property::%s must have a multiline PHPDoc comment with a description before any tags', ucfirst($visibility), $name),
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
