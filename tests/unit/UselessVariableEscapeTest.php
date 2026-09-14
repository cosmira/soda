<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Rules\Usage\UselessVariableAnalyser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UselessVariableEscapeTest extends TestCase
{
    use ParsesPhpSnippets;

    #[DataProvider('escapes')]
    public function testEscapePolicyThroughAliasAnalysis(string $after, int $expected): void
    {
        $nodes = $this->parsePhpFile('<?php function example($source) { $alias = $source; '.$after.' return $alias; }');
        $findings = (new UselessVariableAnalyser)->analyse($nodes);

        self::assertCount($expected, $findings);
    }

    public static function escapes(): array
    {
        return [
            'arrow captures alias'                 => ['$fn = fn () => $alias;', 0],
            'arrow captures other variable'        => ['$fn = fn () => $other;', 1],
            'arrow returns literal'                => ['$fn = fn () => 1;', 1],
            'alias property assigned'              => ['$alias->name = $name;', 0],
            'other object property assigned'       => ['$other->name = $name;', 1],
            'alias property read'                  => ['$name = $alias->name;', 1],
            'closure captures alias'               => ['$fn = function () use ($alias) { return $alias; };', 0],
            'closure without capture'              => ['$fn = function () { return $alias; };', 1],
            'closure captures another variable'    => ['$fn = function () use ($other) { return $other; };', 1],
            'alias passed by reference'            => ['foo(&$alias);', 0],
            'alias passed by value'                => ['foo($alias);', 1],
            'another variable passed by reference' => ['foo(&$other);', 1],
            'source unset'                         => ['unset($source);', 0],
            'another variable unset'               => ['unset($other);', 1],
            'multiple unrelated variables unset'   => ['unset($first, $other);', 1],
        ];
    }
}
