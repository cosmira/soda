<?php

declare(strict_types=1);
/*
 * This file is part of Soda.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bunnivo\Soda;

use Bunnivo\Soda\Quality\Config\ConfigResolver;
use Bunnivo\Soda\Quality\ConfigException;
use Bunnivo\Soda\Quality\QualityConfig;
use PHPUnit\Framework\TestCase;

final class QualityConfigTest extends TestCase
{
    public function testDefaultConfig(): void
    {
        $config = QualityConfig::default();

        $this->assertSame(100, $config->getRule('max_method_length'));
        $this->assertSame(500, $config->getRule('max_class_length'));
        $this->assertSame(3, $config->getRule('max_arguments'));
        $this->assertSame(40, $config->getRule('max_methods_per_class'));
        $this->assertSame(700, $config->getRule('max_file_loc'));
        $this->assertSame(8, $config->getRule('max_cyclomatic_complexity'));
        $this->assertSame(15, $config->getRule('max_classes_per_namespace'));
        $this->assertSame(50, $config->getRule('max_layer_dominance_percentage'));
        $this->assertSame(0, $config->getRule('max_todo_fixme_comments'));
        $this->assertSame(0, $config->getRule('boolean_methods_without_prefix'));
    }

    public function testFromPhpFixture(): void
    {
        $path = __DIR__.'/../config-fixtures/explicit-soda.php';
        $config = QualityConfig::fromPhpConfiguratorFile($path);

        $this->assertNotEmpty($config->pluginCheckers);
        $this->assertTrue($config->noBuiltinRules);
    }

    public function testFromPhpConfiguratorThrowsWhenNotReadable(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Config file not readable');

        QualityConfig::fromPhpConfiguratorFile('/nonexistent/soda.php');
    }

    public function testResolveUsesExplicitPath(): void
    {
        $path = __DIR__.'/../config-fixtures/explicit-soda.php';
        $config = ConfigResolver::resolveConfig([__FILE__], $path);

        $this->assertNotEmpty($config->pluginCheckers);
        $this->assertTrue($config->noBuiltinRules);
    }

    public function testResolveFindsSodaPhp(): void
    {
        $dir = sys_get_temp_dir().'/soda-resolve-php-'.uniqid();
        mkdir($dir, 0700, true);
        $sodaPath = $dir.'/soda.php';
        file_put_contents($sodaPath, <<<'PHP'
<?php
declare(strict_types=1);
use Bunnivo\Soda\Config\Soda;
use Bunnivo\Soda\Plugins\Rules\Structural\MaxMethodLength;
return Soda::configure()
    ->withPaths(['src/'])
    ->with([new MaxMethodLength(90)]);
PHP
        );

        try {
            $config = ConfigResolver::resolveConfig([$dir.'/dummy.php']);
            $this->assertNotEmpty($config->pluginCheckers);
        } finally {
            unlink($sodaPath);
            rmdir($dir);
        }
    }

    public function testResolveReturnsDefaultWhenNoConfigFound(): void
    {
        $noConfigDir = sys_get_temp_dir().'/soda-no-config-'.uniqid();
        mkdir($noConfigDir, 0700, true);

        try {
            $config = ConfigResolver::resolveConfig([$noConfigDir.'/dummy.php']);
            $this->assertSame(100, $config->getRule('max_method_length'));
        } finally {
            rmdir($noConfigDir);
        }
    }

    public function testResolveIgnoresDotSodaPhp(): void
    {
        $dir = sys_get_temp_dir().'/soda-dot-config-'.uniqid();
        mkdir($dir, 0700, true);
        $sodaPath = $dir.'/.soda.php';
        file_put_contents($sodaPath, <<<'PHP'
<?php
declare(strict_types=1);
use Bunnivo\Soda\Config\Soda;
use Bunnivo\Soda\Plugins\Rules\Structural\MaxMethodLength;
return Soda::configure()
    ->withPaths(['src/'])
    ->with([new MaxMethodLength(90)]);
PHP
        );

        try {
            $config = ConfigResolver::resolveConfig([$dir.'/dummy.php']);
            $this->assertSame(100, $config->getRule('max_method_length'));
        } finally {
            unlink($sodaPath);
            rmdir($dir);
        }
    }
}
