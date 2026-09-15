<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Rules\Architecture\NoDynamicInvocation;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NoDynamicInvocationTest extends TestCase
{
    #[DataProvider('calls')]
    public function testExplicitTargetsAndComputedTargets(string $source, int $expected): void
    {
        $code = '<?php '.$source;
        $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse($code);
        $findings = iterator_to_array((new NoDynamicInvocation)->checkFile(new FileFacts('/project/calls.php', $code, $nodes, [])));
        self::assertCount($expected, $findings);
    }

    public static function calls(): iterable
    {
        yield 'literal closure' => ['(function () { return 1; })();', 0];
        yield 'literal arrow' => ['(fn () => 1)();', 0];
        yield 'static literal closure' => ['(static function () { return 1; })();', 0];
        yield 'static literal arrow' => ['(static fn () => 1)();', 0];
        yield 'computed selection' => ['($flag ? fn () => 1 : fn () => 2)();', 1];
        yield 'nested computed call' => ['(function () use ($callback) { return $callback(); })();', 1];
        yield 'variable callback remains strict policy' => ['function run(Closure $callback) { $callback(); }', 1];
        yield 'dynamic method' => ['$object->$method();', 1];
        yield 'dynamic class' => ['new $class();', 1];
        yield 'indirect dispatcher' => ['call_user_func($callback);', 1];
    }
}
