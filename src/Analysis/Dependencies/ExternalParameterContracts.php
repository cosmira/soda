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
                $path = $sources->locate($parent);
                if ($path === null) {
                    continue;
                }

                $declarations = self::read($path);
                $types += $declarations;
                array_push($pending, ...array_values($declarations));
            }
        }

        return $types;
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
