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
            'alias element unset'                  => ['unset($alias[$key]);', 0],
            'nested alias element unset'           => ['unset($alias[$group][$key]);', 0],
            'alias unset'                          => ['unset($alias);', 0],
            'alias element assigned'               => ['$alias[$key] = 1;', 0],
            'alias element appended'               => ['$alias[] = 1;', 0],
            'alias nested element incremented'     => ['++$alias[$group][$key];', 0],
            'alias element compound assignment'    => ['$alias[$key] += 1;', 0],
            'alias element reference assignment'   => ['$alias[$key] =& $other;', 0],
            'alias used only as an index'          => ['unset($other[$alias]);', 1],
            'alias used as written index'          => ['$other[$alias] = 1;', 1],
            'alias element read'                   => ['consume($alias[$key]);', 1],
            'source reassigned'                    => ['$source = 1;', 0],
            'source element unset'                 => ['unset($source[$key]);', 0],
            'source element assigned'              => ['$source[$key] = 1;', 0],
            'source used only as an index'         => ['$other[$source] = 1;', 1],
            'source unset'                         => ['unset($source);', 0],
            'another variable unset'               => ['unset($other);', 1],
            'multiple unrelated variables unset'   => ['unset($first, $other);', 1],
        ];
    }
}
