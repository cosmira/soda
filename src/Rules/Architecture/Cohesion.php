<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Architecture;

use Cosmira\Soda\Analysis\CallableIdentity;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Collects compact method relationships, then resolves declared and composed graphs.
 * No graph node or edge requires a wrapper object, and no AST survives collection.
 */
final class Cohesion
{
    use CohesionComposition;
    use CohesionConnections;
    use CohesionMethods;

    /**
     * Resolved surfaces are cached for one project only.
     *
     * @var array<string, array>
     */
    private array $surfaces = [];

    /**
     * Collect declarations including traits and anonymous types, independent of legacy counts.
     *
     * @param list<Node> $nodes
     *
     * @return array<string, array>
     */
    public function collect(array $nodes): array
    {
        $types = [];
        foreach ((new NodeFinder)->findInstanceOf($nodes, Stmt\ClassLike::class) as $type) {
            if ($type instanceof Stmt\Interface_) {
                continue;
            }

            $name = CallableIdentity::type($type);
            $hooks = array_filter($type->getProperties(), fn (Stmt\Property $property): bool => ($property->hooks ?? []) !== []);
            $types[$name] = [
                'name'       => $name, 'line' => $type->getStartLine(),
                'kind'       => $type instanceof Stmt\Trait_ ? 'trait' : 'class',
                'parent'     => $type instanceof Stmt\Class_ ? $type->extends?->toString() : null,
                'methods'    => $this->methods($type),
                'properties' => $this->properties($type),
                'uses'       => $this->traitUses($type),
                'unknown'    => $hooks === [] ? [] : ['property hooks require accessor edges'],
            ];
        }

        return $types;
    }

    /**
     * Record declared instance fields, including constructor promotion.
     *
     * @return array<string, string>
     */
    private function properties(Stmt\ClassLike $type): array
    {
        $properties = [];
        foreach ($type->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            foreach ($property->props as $item) {
                $properties[$item->name->toString()] = $this->visibility($property->flags);
            }
        }

        foreach ($type->getMethod('__construct')?->params ?? [] as $parameter) {
            $isPromoted = $parameter->flags !== 0 && $parameter->var instanceof Expr\Variable;
            if ($isPromoted) {
                $properties[$parameter->var->name] = $this->visibility($parameter->flags);
            }
        }

        return $properties;
    }

    /**
     * Decode PHP visibility without interpreting a role or naming convention.
     */
    private function visibility(int $flags): string
    {
        return match (true) {
            ($flags & Stmt\Class_::MODIFIER_PRIVATE) !== 0   => 'private',
            ($flags & Stmt\Class_::MODIFIER_PROTECTED) !== 0 => 'protected',
            default                                          => 'public',
        };
    }

    /**
     * Keep the exact trait adaptation syntax as scalar facts for project resolution.
     *
     * @return list<array>
     */
    private function traitUses(Stmt\ClassLike $type): array
    {
        $uses = [];
        foreach ($type->getTraitUses() as $use) {
            $adaptations = [];
            foreach ($use->adaptations as $adaptation) {
                $row = ['trait' => $adaptation->trait?->toLowerString(), 'method' => $adaptation->method->toLowerString()];
                if ($adaptation instanceof Stmt\TraitUseAdaptation\Alias) {
                    $row += ['kind'  => 'alias', 'alias' => $adaptation->newName?->toLowerString(),
                        'visibility' => $adaptation->newModifier === null ? null : $this->visibility($adaptation->newModifier)];
                }

                if ($adaptation instanceof Stmt\TraitUseAdaptation\Precedence) {
                    $row += ['kind' => 'precedence', 'instead' => array_map(fn (Node\Name $name): string => $name->toLowerString(), $adaptation->insteadof)];
                }

                $adaptations[] = $row;
            }

            $uses[] = ['traits' => array_map(fn (Node\Name $name): string => $name->toLowerString(), $use->traits), 'adaptations' => $adaptations];
        }

        return $uses;
    }

    /**
     * Build both graph views after the project has supplied all available declarations.
     *
     * @param array<string, array> $files
     *
     * @return array<string, array>
     */
    public function resolve(array $files): array
    {
        $this->surfaces = [];
        $types = [];
        foreach ($files as $path => $file) {
            foreach ($file['cohesion'] ?? [] as $name => $type) {
                $key = $this->typeKey($name, $path);
                if (isset($types[$key])) {
                    $type['unknown'] = [...$type['unknown'], 'duplicate declaration '.$name];
                }

                $types[$key] = $type;
            }
        }

        foreach ($files as $path => &$file) {
            $views = [];
            $classes = $file['classes'];
            foreach ($file['cohesion'] ?? [] as $name => $type) {
                $key = $this->typeKey($name, $path);
                $declared = $this->connections($type);
                $composed = $this->connections($this->surface($key, $types));
                $views[$name] = array_replace($type, ['declared' => $declared, 'composed' => $composed]);
                if (isset($classes[$name])) {
                    $classes[$name] = array_replace($classes[$name], $composed['metrics']);
                }
            }

            $file['classes'] = $classes;
            $file['cohesion'] = $views;
        }

        unset($file);

        return $files;
    }

    /**
     * Source-local anonymous names must not collide between files in project aggregation.
     */
    private function typeKey(string $name, string $path): string
    {
        return str_starts_with($name, '{anonymous}') ? $path.'#'.$name : strtolower($name);
    }
}
