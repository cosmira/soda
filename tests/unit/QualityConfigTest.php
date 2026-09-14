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

namespace Cosmira\Soda;

use Cosmira\Soda\Config\ConfigException;
use Cosmira\Soda\Config\ConfigLoader;
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\SodaConfig;
use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class QualityConfigTest extends TestCase
{
    public function testDefaultConfig(): void
    {
        $config = CheckFixture::thresholds();

        $this->assertSame(100, CheckFixture::thresholds()['max_method_length']);
        $this->assertSame(500, CheckFixture::thresholds()['max_class_length']);
        $this->assertSame(3, CheckFixture::thresholds()['max_arguments']);
        $this->assertSame(40, CheckFixture::thresholds()['max_methods_per_class']);
        $this->assertSame(700, CheckFixture::thresholds()['max_file_loc']);
        $this->assertSame(8, CheckFixture::thresholds()['max_cyclomatic_complexity']);
        $this->assertSame(15, CheckFixture::thresholds()['max_classes_per_namespace']);
        $this->assertSame(50, CheckFixture::thresholds()['max_layer_dominance_percentage']);
        $this->assertSame(0, CheckFixture::thresholds()['max_todo_fixme_comments']);
        $this->assertSame(0, CheckFixture::thresholds()['boolean_methods_without_prefix']);
        $this->assertSame(32, CheckFixture::thresholds()['namespace_name_length']);
    }

    public function testFromPhpFixture(): void
    {
        $path = __DIR__.'/../config-fixtures/explicit-soda.php';
        $config = (new ConfigLoader())->load($path);

        $this->assertNotEmpty($config->checks());
        $this->assertInstanceOf(SodaConfig::class, $config);
    }

    public function testStandardRulesInPhpConfigEnablesEveryCatalogThreshold(): void
    {
        $config = (new ConfigLoader())->load(dirname(__DIR__, 2).'/demo-app/soda.php');

        $this->assertSame(array_keys(RuleCatalog::definitions()), array_map(fn ($rule) => $rule->id(), $config->checks()));
        $this->assertInstanceOf(SodaConfig::class, $config);
        $this->assertNotEmpty($config->checks());
    }

    public function testFromPhpConfiguratorThrowsWhenNotReadable(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Config file not readable');

        (new ConfigLoader())->load('/nonexistent/soda.php');
    }

    public function testResolveUsesExplicitPath(): void
    {
        $path = __DIR__.'/../config-fixtures/explicit-soda.php';
        $config = (new ConfigLoader)->resolve([__FILE__], $path);

        $this->assertNotEmpty($config->checks());
        $this->assertInstanceOf(SodaConfig::class, $config);
    }

    public function testResolveFindsSodaPhp(): void
    {
        $dir = sys_get_temp_dir().'/soda-resolve-php-'.uniqid();
        mkdir($dir, 0700, true);
        $sodaPath = $dir.'/soda.php';
        file_put_contents($sodaPath, <<<'PHP'
<?php
declare(strict_types=1);
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Structure\MaxMethodLength;
return Soda::configure()
    ->withPaths(['src/'])
    ->with([new MaxMethodLength(90)]);
PHP
        );

        try {
            $config = (new ConfigLoader)->resolve([$dir.'/dummy.php']);
            $this->assertNotEmpty($config->checks());
        } finally {
            unlink($sodaPath);
            rmdir($dir);
        }
    }

    public function testResolveFindsSodaPhpWhenInputIsDirectory(): void
    {
        $dir = sys_get_temp_dir().'/soda-resolve-dir-'.uniqid();
        mkdir($dir, 0700, true);
        $sodaPath = $dir.'/soda.php';
        file_put_contents($sodaPath, <<<'PHP'
<?php
declare(strict_types=1);
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Structure\MaxMethodLength;
return Soda::configure()
    ->withPaths(['src/'])
    ->with([new MaxMethodLength(90)]);
PHP
        );

        try {
            $config = (new ConfigLoader)->resolve([$dir]);
            $this->assertNotEmpty($config->checks());
            $this->assertInstanceOf(SodaConfig::class, $config);
        } finally {
            unlink($sodaPath);
            rmdir($dir);
        }
    }

    public function testResolveRejectsMultipleImplicitConfigs(): void
    {
        $left = sys_get_temp_dir().'/soda-resolve-left-'.uniqid();
        $right = sys_get_temp_dir().'/soda-resolve-right-'.uniqid();
        mkdir($left, 0700, true);
        mkdir($right, 0700, true);
        $leftConfig = $left.'/soda.php';
        $rightConfig = $right.'/soda.php';
        file_put_contents($leftConfig, $this->minimalSodaConfig());
        file_put_contents($rightConfig, $this->minimalSodaConfig());

        try {
            $this->expectException(ConfigException::class);
            $this->expectExceptionMessage('Multiple soda.php configs found');
            $this->expectExceptionMessage($leftConfig);
            $this->expectExceptionMessage($rightConfig);

            (new ConfigLoader)->resolve([$left, $right]);
        } finally {
            unlink($leftConfig);
            unlink($rightConfig);
            rmdir($left);
            rmdir($right);
        }
    }

    public function testResolveReturnsDefaultWhenNoConfigFound(): void
    {
        $noConfigDir = sys_get_temp_dir().'/soda-no-config-'.uniqid();
        mkdir($noConfigDir, 0700, true);

        try {
            $config = (new ConfigLoader)->resolve([$noConfigDir.'/dummy.php']);
            $this->assertSame(100, CheckFixture::thresholds()['max_method_length']);
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
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Structure\MaxMethodLength;
return Soda::configure()
    ->withPaths(['src/'])
    ->with([new MaxMethodLength(90)]);
PHP
        );

        try {
            $config = (new ConfigLoader)->resolve([$dir.'/dummy.php']);
            $this->assertSame(100, CheckFixture::thresholds()['max_method_length']);
        } finally {
            unlink($sodaPath);
            rmdir($dir);
        }
    }

    private function minimalSodaConfig(): string
    {
        return <<<'PHP'
<?php
declare(strict_types=1);
use Cosmira\Soda\Config\Soda;
return Soda::configure()->withPaths(['src/'])->with([]);
PHP;
    }
}
