<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis\Dependencies;

use Cosmira\Soda\Analysis\InheritedParameterContracts;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;

/**
 * Reads only missing ancestor declarations; dependency code is never executed or linted.
 */
final readonly class ExternalParameterContracts
{
    /**
     * Resolve reachable ancestors from installed dependency source metadata.
     */
    public static function resolve(array $types, array $files): array
    {
        $sources = ComposerTypeSources::discover($files);
        $pending = array_values($types);
        $visited = array_fill_keys(array_keys($types), true);
        while ($pending !== []) {
            $type = array_pop($pending);
            foreach ([...($type['parentNames'] ?? []), ...($type['traitNames'] ?? [])] as $parent) {
                $key = strtolower($parent);
                if (isset($visited[$key])) {
                    continue;
                }

                $visited[$key] = true;
                $declarations = self::declarationsFor($parent, $sources);
                $types += $declarations;
                array_push($pending, ...array_values($declarations));
            }
        }

        return $types;
    }

    /**
     * Verify the qualified declaration so matching filenames cannot exempt unrelated parameters.
     */
    private static function declarationsFor(string $class, ComposerTypeSources $sources): array
    {
        $key = strtolower($class);
        foreach ($sources->locate($class) as $path) {
            $declarations = self::read($path);
            if (isset($declarations[$key])) {
                return $declarations;
            }
        }

        return [];
    }

    /**
     * Parse signature facts with resolved names and lexical scopes, never require the source.
     */
    private static function read(string $path): array
    {
        $source = file_get_contents($path);
        if ($source === false) {
            return [];
        }

        try {
            $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse($source) ?? [];
            $nodes = (new NodeTraverser(new NameResolver, new ParentConnectingVisitor))->traverse($nodes);

            return InheritedParameterContracts::collect($nodes);
        } catch (Error) {
            return [];
        }
    }
}
