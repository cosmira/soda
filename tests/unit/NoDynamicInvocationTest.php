<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Rules\Architecture\NoDynamicInvocation;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
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
        $nodes = (new NodeTraverser(new NameResolver, new ParentConnectingVisitor))->traverse($nodes);
        $findings = iterator_to_array((new NoDynamicInvocation)->checkFile(new FileFacts('/project/calls.php', $code, $nodes, [])));
        self::assertCount($expected, $findings);
    }

    public static function calls(): iterable
    {
        yield 'literal closure' => ['(function () { return 1; })();', 0];
        yield 'literal arrow' => ['(fn () => 1)();', 0];
        yield 'static literal closure' => ['(static function () { return 1; })();', 0];
        yield 'static literal arrow' => ['(static fn () => 1)();', 0];
        yield 'computed selection' => ['($flag ? fn () => 1 : fn () => 2)();', 0];
        yield 'nested computed call' => ['(function () use ($callback) { return $callback(); })();', 0];
        yield 'declared closure contract' => ['function run(Closure $callback) { $callback(); }', 0];
        yield 'nullsafe dynamic method' => ['$object?->$method();', 1];
        yield 'dynamic method' => ['$object->$method();', 1];
        yield 'typed model construction' => ['/** @param class-string<Model> $model */ function build(string $model): Model { return new $model(); }', 0];
        yield 'dynamic class' => ['new $class();', 0];
        yield 'indirect dispatcher' => ['call_user_func($callback);', 1];
    }
}
