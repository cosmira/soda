<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Naming;

/**
 * Splits camelCase and snake_case while keeping consecutive capitals as one acronym.
 *
 * @internal
 */
final readonly class CompoundIdentifierWords
{
    /**
     * @return list<non-empty-string>
     */
    public static function split(string $name): array
    {
        $words = [];
        $segments = preg_split('/_+/', trim($name, '_'));

        if ($segments === false) {
            return [];
        }

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            $matches = [];
            preg_match_all('/[A-Z]+(?=[A-Z][a-z]|$)\d*|[A-Z]?[a-z]+\d*/', $segment, $matches);
            $segmentWords = array_shift($matches) ?? [];

            if ($segmentWords === []) {
                $segmentWords = [$segment];
            }

            foreach ($segmentWords as $word) {
                $isNonemptyWord = is_string($word) && $word !== '';
                if ($isNonemptyWord) {
                    $words[] = strtolower($word);
                }
            }
        }

        return $words;
    }
}
