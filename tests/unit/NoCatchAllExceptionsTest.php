<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Rules\Architecture\NoCatchAllExceptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NoCatchAllExceptionsTest extends TestCase
{
    use ParsesPhpSnippets;

    #[DataProvider('catchBodies')]
    public function testReportsOnlyBroadCatchesWithoutAnExplicitOutcome(string $body, int $expected): void
    {
        $source = 'function run() { try { work(); } catch (\\Throwable $error) { '.$body.' } }';
        $file = new FileFacts('/handler.php', '', $this->parseSnippet($source), []);
        $violations = iterator_to_array((new NoCatchAllExceptions)->checkFile($file));

        $this->assertCount($expected, $violations);
        if ($expected > 0) {
            $this->assertSame('no_catch_all_exceptions', $violations[0]->rule);
            $this->assertSame(1, $violations[0]->line);
        }
    }

    public static function catchBodies(): \Iterator
    {
        yield 'empty catch' => ['', 1];
        yield 'logging then continuing' => ['report($error);', 1];
        yield 'void guard before fallback' => ['if (recoverable($error)) { return; } return null;', 1];
        yield 'closure void return does not affect fallback' => ['$callback = function () { return; }; return null;', 0];
        yield 'bare return' => ['return;', 1];
        yield 'null fallback' => ['return null;', 0];
        yield 'false fallback' => ['return false;', 0];
        yield 'structured error' => ['return ["error" => $error->getMessage()];', 0];
        yield 'cleanup then rethrow' => ['cleanup(); throw $error;', 0];
        yield 'translated exception' => ['throw new RuntimeException("Failed", 0, $error);', 0];
        yield 'all branches handled' => ['if (recoverable($error)) { return null; } else { throw $error; }', 0];
        yield 'elseif handled' => ['if (first($error)) { return null; } elseif (second($error)) { return false; } else { throw $error; }', 0];
        yield 'one branch continues' => ['if (recoverable($error)) { return null; } else { report($error); }', 1];
        yield 'conditional return only' => ['if (recoverable($error)) { return null; }', 1];
        yield 'guard then rethrow' => ['if (recoverable($error)) { return null; } throw $error;', 0];
        yield 'closure return is not catch outcome' => ['$fallback = fn () => null;', 1];
        yield 'nested closure return' => ['$fallback = function () { return null; };', 1];
    }

    public function testSpecificExceptionsAreAllowedWithoutAnOutcome(): void
    {
        $file = new FileFacts('/handler.php', '', $this->parseSnippet(
            'try { work(); } catch (RuntimeException $error) { report($error); }',
        ), []);

        $this->assertSame([], iterator_to_array((new NoCatchAllExceptions)->checkFile($file)));
    }
}
