<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Rules\Architecture\NoModeParameters;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NoModeParametersTest extends TestCase
{
    #[DataProvider('cases')]
    public function testSelectorsAndDataBoundaries(string $body, int $expected): void
    {
        $source = '<?php function execute($mode) { '.$body.' }';
        $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse($source);
        $findings = iterator_to_array((new NoModeParameters)->checkFile(new FileFacts('/project/modes.php', $source, $nodes, [])));
        self::assertCount($expected, $findings);
        if ($expected !== 0) {
            self::assertSame(1, $findings[0]->line);
            self::assertSame(1, $findings[0]->value);
            self::assertSame(0, $findings[0]->threshold);
            self::assertStringContainsString('$mode', $findings[0]->message);
        }
    }

    public static function cases(): iterable
    {
        yield 'switch' => ['switch ($mode) { case "new": create(); break; case "old": update(); break; }', 1];
        yield 'match' => ['return match ($mode) { 0 => create(), 1 => update() };', 1];
        yield 'enum' => ['return match ($mode) { Mode::Create => create(), Mode::Update => update() };', 1];
        yield 'if chain' => ['if ($mode === "new") { create(); } elseif ($mode === "old") { update(); }', 1];
        yield 'reversed equality' => ['if ("new" === $mode) { create(); } else { update(); }', 1];
        yield 'default switch' => ['switch ($mode) { case 0: create(); break; default: update(); }', 1];
        yield 'default match' => ['return match ($mode) { 0 => create(), default => update() };', 1];
        yield 'direct aliases' => ['$first = $mode; $second = $first; return match ($second) { 0 => create(), 1 => update() };', 1];
        yield 'branch alias' => ['if (ready()) { $selected = $mode; } else { $selected = "new"; } return match ($selected) { 0 => create(), 1 => update() };', 1];
        yield 'overwritten alias' => ['$selected = $mode; $selected = 0; return match ($selected) { 0 => create(), 1 => update() };', 0];
        yield 'shared preparation' => ['switch ($mode) { case 0: prepare(); create(); break; case 1: prepare(); update(); break; }', 1];
        yield 'different receivers' => ['return match ($mode) { 0 => $sales->save(), 1 => $billing->save() };', 1];
        yield 'scalar data' => ['return match ($mode) { "en" => "Hello", "fr" => "Bonjour" };', 0];
        yield 'same operation different data' => ['return match ($mode) { "en" => format("Hello"), "fr" => format("Bonjour") };', 0];
        yield 'validation only' => ['if ($mode === "unknown") { throw new InvalidArgumentException(); } create();', 0];
        yield 'unrelated local selector' => ['$local = random_int(0, 1); return match ($local) { 0 => create(), 1 => update() };', 0];
        yield 'nested scope isolated' => ['$closure = function () { return match ($mode) { 0 => create(), 1 => update() }; };', 0];
    }
}
