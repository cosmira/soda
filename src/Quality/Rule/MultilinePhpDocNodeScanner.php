<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\Rule;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

final class MultilinePhpDocNodeScanner
{
    public function __construct(private MultilinePhpDocComment $phpDoc = new MultilinePhpDocComment()) {}

    /**
     * @param list<string> $rules
     *
     * @return list<MultilinePhpDocOccurrence>
     */
    public function scan(string $file, array $rules): array
    {
        $nodes = $this->parse($file);

        if ($nodes === []) {
            return [];
        }

        $out = [];

        foreach ($this->classLikes($nodes) as $class) {
            if (in_array(MultilinePhpDocChecker::METHOD_RULE, $rules, true)) {
                array_push($out, ...$this->methodOccurrences($class));
            }

            if (in_array(MultilinePhpDocChecker::PROPERTY_RULE, $rules, true)) {
                array_push($out, ...$this->propertyOccurrences($class));
            }
        }

        return $out;
    }

    /**
     * @return list<Node>
     */
    private function parse(string $file): array
    {
        $source = @file_get_contents($file);

        if ($source === false || $source === '') {
            return [];
        }

        try {
            return (new ParserFactory)->createForNewestSupportedVersion()->parse($source) ?? [];
        } catch (Error) {
            return [];
        }
    }

    /**
     * @param list<Node> $nodes
     *
     * @return list<Class_|Interface_|Trait_|Enum_>
     */
    private function classLikes(array $nodes): array
    {
        $nodes = (new NodeFinder)->find(
            $nodes,
            static fn (Node $node): bool => self::isSupportedClassLike($node),
        );
        $classes = [];

        foreach ($nodes as $node) {
            if ($node instanceof Class_ || $node instanceof Interface_ || $node instanceof Trait_ || $node instanceof Enum_) {
                $classes[] = $node;
            }
        }

        return $classes;
    }

    private static function isSupportedClassLike(Node $node): bool
    {
        return $node instanceof Class_
            || $node instanceof Interface_
            || $node instanceof Trait_
            || $node instanceof Enum_;
    }

    /**
     * @return list<MultilinePhpDocOccurrence>
     */
    private function methodOccurrences(Class_|Interface_|Trait_|Enum_ $class): array
    {
        $out = [];

        foreach ($class->getMethods() as $method) {
            $out[] = new MultilinePhpDocOccurrence(
                MultilinePhpDocChecker::METHOD_RULE,
                $method->name->toString(),
                $this->methodVisibility($method),
                new MultilinePhpDocLocation($method->getLine(), $this->className($class)),
                $this->phpDoc->isValid($method),
            );
        }

        return $out;
    }

    /**
     * @return list<MultilinePhpDocOccurrence>
     */
    private function propertyOccurrences(Class_|Interface_|Trait_|Enum_ $class): array
    {
        if ($class instanceof Interface_) {
            return [];
        }

        $out = [];

        foreach ($class->getProperties() as $property) {
            foreach ($property->props as $prop) {
                $out[] = new MultilinePhpDocOccurrence(
                    MultilinePhpDocChecker::PROPERTY_RULE,
                    $prop->name->toString(),
                    $this->propertyVisibility($property),
                    new MultilinePhpDocLocation($property->getLine(), $this->className($class)),
                    $this->phpDoc->isValid($property),
                );
            }
        }

        return $out;
    }

    private function methodVisibility(ClassMethod $method): string
    {
        if ($method->isProtected()) {
            return 'protected';
        }

        if ($method->isPrivate()) {
            return 'private';
        }

        return 'public';
    }

    private function propertyVisibility(Property $property): string
    {
        if ($property->isProtected()) {
            return 'protected';
        }

        if ($property->isPrivate()) {
            return 'private';
        }

        return 'public';
    }

    private function className(ClassLike $class): string
    {
        if ($class->name !== null) {
            return $class->name->toString();
        }

        return 'anonymous@'.$class->getLine();
    }
}
