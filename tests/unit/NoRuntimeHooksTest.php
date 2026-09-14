<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Rules\Architecture\NoRuntimeHooks;
use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NoRuntimeHooksTest extends TestCase
{
    #[DataProvider('calls')]
    public function testReportsOnlyTheConfiguredPhpHooks(string $source, array $lines): void
    {
        $path = tempnam(sys_get_temp_dir(), 'soda-runtime-hooks-');
        file_put_contents($path, "<?php\n".$source);

        try {
            $violations = CheckFixture::collect($path, [new NoRuntimeHooks])['violations'];
        } finally {
            unlink($path);
        }

        $this->assertSame($lines, array_column($violations, 'line'));
        foreach ($violations as $violation) {
            $this->assertSame('no_runtime_hooks', $violation->rule);
            $this->assertSame($path, $violation->file);
            $this->assertSame(1, $violation->value);
            $this->assertSame(0, $violation->threshold);
            $this->assertSame(
                'Global runtime hooks create non-local behavior. Keep lifecycle and error handling at an explicit boundary.',
                $violation->message,
            );
        }
    }

    public static function calls(): iterable
    {
        yield 'shutdown' => ['register_shutdown_function($callback);', [2]];
        yield 'error handler' => ['set_error_handler($callback);', [2]];
        yield 'case insensitive' => ['SET_ERROR_HANDLER($callback);', [2]];
        yield 'fully qualified' => ['\set_error_handler($callback);', [2]];
        yield 'namespace fallback' => ['namespace App; set_error_handler($callback);', [2]];
        yield 'function import' => ['namespace App; use function set_error_handler as onError; onError($callback);', [2]];
        yield 'first class callable preserves syntax policy' => ['set_error_handler(...);', [2]];
        yield 'each occurrence' => ["set_error_handler(\$callback); register_shutdown_function(\$callback);\nset_error_handler(\$callback);", [2, 2, 3]];
        yield 'nested callable' => ['function run() { return function () { set_error_handler($callback); }; }', [2]];
        yield 'anonymous class' => ['new class { public function run() { set_error_handler($callback); } };', [2]];
        yield 'unrelated namespaced function' => ['\App\set_error_handler($callback);', []];
        yield 'method with same name' => ['$object->set_error_handler($callback);', []];
        yield 'static method with same name' => ['Handler::set_error_handler($callback);', []];
        yield 'dynamic call belongs to another rule' => ['$callback = "set_error_handler"; $callback($handler);', []];
        yield 'exception handler remains outside existing policy' => ['set_exception_handler($callback);', []];
    }
}
