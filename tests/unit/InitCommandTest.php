<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Commands;

use Bunnivo\Soda\Application;
use Bunnivo\Soda\Quality\QualityConfig;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\OutputStyle;
use Illuminate\Events\Dispatcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class InitCommandTest extends TestCase
{
    public function testInitCreatesFileWithAllPossibleRules(): void
    {
        $dir = sys_get_temp_dir().'/soda-init-test-'.uniqid();
        mkdir($dir, 0700, true);
        $path = $dir.'/soda.php';

        $cwd = getcwd();
        chdir($dir);

        try {
            $container = new Application();
            $artisan = new ConsoleApplication($container, new Dispatcher($container), '8.0');
            $artisan->setAutoExit(false);
            $artisan->add(new InitCommand());

            $input = new ArrayInput(['command' => 'init']);
            $output = new BufferedOutput();

            $exitCode = $artisan->run($input, new OutputStyle($input, $output));

            $this->assertSame(0, $exitCode);
            $this->assertFileExists($path);

            $content = file_get_contents($path);
            $this->assertNotFalse($content);
            $this->assertStringContainsString('Soda::configure()', $content);
            $this->assertStringContainsString('->withPaths([', $content);
            $this->assertStringContainsString("'src/'", $content);
            $this->assertStringContainsString('->with([', $content);
            $this->assertStringContainsString('new MaxMethodLength(', $content);
            $this->assertStringContainsString('new MaxMethodLength(100)', $content);
            $this->assertStringContainsString('new MaxFileLoc(700)', $content);
            $this->assertStringContainsString('new MaxLineLength(100)', $content);
            $this->assertStringContainsString('new VariableNameLength(min: 3, max: 16)', $content);
            $this->assertStringContainsString('new MethodNameLength(min: 3, max: 32)', $content);
            $this->assertStringContainsString('new ClassNameLength(min: 3, max: 32)', $content);
            $this->assertStringContainsString('new MaxLayerDominancePercentage(', $content);
            $this->assertStringContainsString('new OnlyListArraysAllowed(', $content);
            $this->assertStringContainsString('new NoNumericArrayIndex(', $content);
            $this->assertStringContainsString('new NoUnusedMethods(', $content);
            $this->assertStringContainsString('new UselessVariableRule(', $content);
            $this->assertStringContainsString('new MethodsFollowCallOrder(0)', $content);
            $this->assertStringContainsString("new MultilineMethodPhpDoc(['public'])", $content);
            $this->assertStringContainsString("new MultilinePropertyPhpDoc(['public'])", $content);

            $config = QualityConfig::fromPhpConfiguratorFile($path);

            $this->assertNotEmpty($config->pluginCheckers);
            $this->assertTrue($config->noBuiltinRules);
            $this->assertSame(['src/'], $config->paths);
        } finally {
            chdir($cwd);
            if (is_file($path)) {
                unlink($path);
            }

            rmdir($dir);
        }
    }

    public function testInitFailsWhenSodaPhpAlreadyExists(): void
    {
        $dir = sys_get_temp_dir().'/soda-init-fail-'.uniqid();
        mkdir($dir, 0700, true);
        file_put_contents($dir.'/soda.php', "<?php\nreturn static function (): void {};\n");

        $cwd = getcwd();
        chdir($dir);

        try {
            $container = new Application();
            $artisan = new ConsoleApplication($container, new Dispatcher($container), '8.0');
            $artisan->setAutoExit(false);
            $artisan->add(new InitCommand());

            $input = new ArrayInput(['command' => 'init']);
            $output = new BufferedOutput();

            $exitCode = $artisan->run($input, new OutputStyle($input, $output));

            $this->assertSame(1, $exitCode);
        } finally {
            chdir($cwd);
            unlink($dir.'/soda.php');
            rmdir($dir);
        }
    }
}
