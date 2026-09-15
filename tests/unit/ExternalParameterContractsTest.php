<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Usage\NoUnusedParameters;
use PHPUnit\Framework\TestCase;

final class ExternalParameterContractsTest extends TestCase
{
    public function testComposerDependencyContractIsParsedWithoutExecution(): void
    {
        $this->withProject(function (string $root): void {
            $this->write($root, 'vendor/vendor/api/src/Scope.php', '<?php namespace Vendor\Api; file_put_contents(__DIR__."/executed", "bad"); interface Scope { public function apply($builder, $model); }');
            $this->write($root, 'vendor/autoload.php', '<?php throw new RuntimeException("Autoloader must not execute");');
            $path = $this->write($root, 'src/OwnScope.php', '<?php namespace App; class OwnScope implements \Vendor\Api\Scope { public function apply($builder, $model) {} }');
            self::assertSame([], $this->findings([$path]));
            self::assertFileDoesNotExist($root.'/vendor/vendor/api/src/executed');
        });
    }

    public function testExternalAncestorsResolveTransitivelyAndExtraInputsRemainChecked(): void
    {
        $this->withProject(function (string $root): void {
            $this->write($root, 'vendor/vendor/api/src/BaseScope.php', '<?php namespace Vendor\Api; interface BaseScope { public function apply($builder, $model); }');
            $this->write($root, 'vendor/vendor/api/src/Scope.php', '<?php namespace Vendor\Api; interface Scope extends BaseScope {}');
            $path = $this->write($root, 'src/OwnScope.php', '<?php namespace App; class OwnScope implements \Vendor\Api\Scope { public function apply($builder, $model, $extra = null) {} }');
            $findings = $this->findings([$path]);
            self::assertCount(1, $findings);
            self::assertSame($path, $findings[0]->file);
            self::assertStringContainsString('$extra', $findings[0]->message);
        });
    }

    public function testUnresolvedOrUnrelatedDependencyDoesNotExemptInput(): void
    {
        $this->withProject(function (string $root): void {
            $this->write($root, 'vendor/vendor/api/src/Scope.php', '<?php namespace Vendor\Api; interface Scope { public function apply($builder, $model); }');
            $path = $this->write($root, 'src/OwnScope.php', '<?php namespace App; class OwnScope implements \Missing\Scope { public function apply($builder, $model) {} }');
            self::assertCount(2, $this->findings([$path]));
            $this->write($root, 'vendor/vendor/api/src/Scope.php', '<?php broken syntax !!!');
            $this->write($root, 'src/OwnScope.php', '<?php namespace App; class OwnScope implements \Vendor\Api\Scope { public function apply($builder, $model) {} }');
            self::assertCount(2, $this->findings([$path]));
        });
    }

    private function findings(array $paths): array
    {
        return (new Runner)->check($paths, Soda::configure()->with([new NoUnusedParameters]))->violations->all();
    }

    private function withProject(callable $test): void
    {
        $root = tempnam(sys_get_temp_dir(), 'soda-composer-contract-');
        unlink($root);
        mkdir($root);

        try {
            $this->write($root, 'vendor/composer/installed.json', json_encode(['packages' => [[
                'name'     => 'vendor/api', 'install-path' => '../vendor/api',
                'autoload' => ['psr-4' => ['Vendor\\Api\\' => ['missing/', 'src/']]],
            ]]], JSON_THROW_ON_ERROR));
            $test($root);
        } finally {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $entry) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($root);
        }
    }

    private function write(string $root, string $relative, string $source): string
    {
        $path = $root.'/'.$relative;
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), recursive: true);
        }
        file_put_contents($path, $source);

        return $path;
    }
}
