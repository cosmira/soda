<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\Soda;
use PHPUnit\Framework\TestCase;

final class LaravelMiddlewareContractsTest extends TestCase
{
    public function testRegisteredTerminateContractAcrossFilesAndOrder(): void
    {
        $provider = '<?php class Provider extends \Illuminate\Support\ServiceProvider { function boot() { $this->app["router"]->aliasMiddleware("sandbox", Middleware::class); } }';
        $middleware = '<?php class Middleware { public function terminate($request, $response) {} }';
        self::assertSame([], $this->findings([$provider, $middleware]));
        self::assertSame([], $this->findings([$middleware, $provider]));
        $named = str_replace('"sandbox", Middleware::class', 'class: Middleware::class, name: "sandbox"', $provider);
        self::assertSame([], $this->findings([$named, $middleware]));
        self::assertCount(2, $this->findings([$middleware]));
        self::assertCount(2, $this->findings([str_replace('Illuminate\Support\ServiceProvider', 'App\ServiceProvider', $provider), $middleware]));
        self::assertCount(2, $this->findings([str_replace('Middleware::class', 'OtherMiddleware::class', $provider), $middleware]));
        self::assertCount(2, $this->findings([$provider, str_replace('public function', 'protected function', $middleware)]));
        self::assertCount(2, $this->findings([$provider, str_replace('public function', 'public static function', $middleware)]));
        $extra = str_replace('$response)', '$response, $extra)', $middleware);
        $findings = $this->findings([$provider, $extra]);
        self::assertCount(1, $findings);
        self::assertStringContainsString('$extra', $findings[0]->message);
    }

    private function findings(array $sources): array
    {
        $files = [];

        try {
            foreach ($sources as $source) {
                $file = tempnam(sys_get_temp_dir(), 'soda-middleware-');
                file_put_contents($file, $source);
                $files[] = $file;
            }

            return (new Runner)->check($files, Soda::configure()->with(RuleCatalog::standard()))->violations
                ->filter(static fn ($finding): bool => $finding->rule === 'no_unused_parameters')->values()->all();
        } finally {
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }
}
