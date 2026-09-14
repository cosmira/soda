<?php

declare(strict_types=1);

namespace Cosmira\Soda\Commands;

use Cosmira\Soda\Application;
use Cosmira\Soda\Config\ConfigLoader;
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\SodaConfig;
use Cosmira\Soda\Config\SodaInitFileEmitter;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\OutputStyle;
use Illuminate\Events\Dispatcher;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
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

            foreach (RuleCatalog::definitions() as $id => $init) {
                $expression = $id === 'boolean_methods_without_prefix'
                    ? 'BooleanMethodPrefix::standard()'
                    : 'new '.$this->shortName($init['class']).'(';
                $this->assertStringContainsString($expression, $content, $id);
            }

            $this->assertStringContainsString('new MaxMethodLength(100)', $content);
            $this->assertStringContainsString('new MaxFileLoc(700)', $content);
            $this->assertStringContainsString('new MaxLineLength(100)', $content);
            $this->assertStringContainsString('new VariableNameLength(min: 3, max: 16)', $content);
            $this->assertStringContainsString('new MethodNameLength(min: 3, max: 32)', $content);
            $this->assertStringContainsString('new ClassNameLength(min: 3, max: 32)', $content);
            $this->assertStringContainsString('new NamespaceNameLength(min: 3, max: 32)', $content);
            $this->assertStringContainsString('new MaxLayerDominancePercentage(', $content);
            $this->assertStringContainsString('new MethodsFollowCallOrder()', $content);
            $this->assertStringNotContainsString('MultilineMethodPhpDoc', $content);
            $this->assertStringNotContainsString('MultilinePropertyPhpDoc', $content);
            $this->assertStringNotContainsString('MultilineConstantPhpDoc', $content);

            $config = (new ConfigLoader())->load($path);

            $this->assertEquals(RuleCatalog::standard(), $config->checks());
            $rulesById = [];
            foreach ($config->checks() as $check) {
                $rulesById[$check->id()] = $check;
            }
            $properties = new ReflectionProperty($rulesById['max_arguments'], 'properties');
            $this->assertSame($rulesById['max_properties_per_class'], $properties->getValue($rulesById['max_arguments']));
            $this->assertSame(
                array_keys(RuleCatalog::definitions()),
                array_map(static fn ($check): string => $check->id(), $config->checks()),
            );
            $this->assertNotEmpty($config->checks());
            $this->assertCount(count(RuleCatalog::definitions()), $config->checks());
            $checkerClasses = array_map(static fn (object $checker): string => $checker::class, $config->checks());
            $this->assertSame(array_values(array_unique($checkerClasses)), $checkerClasses);
            $this->assertInstanceOf(SodaConfig::class, $config);
            $this->assertSame(['src/'], $config->paths());
        } finally {
            chdir($cwd);
            if (is_file($path)) {
                unlink($path);
            }

            rmdir($dir);
        }
    }

    private function shortName(string $fqcn): string
    {
        return substr($fqcn, strrpos($fqcn, '\\') + 1);
    }

    public function testInitEmitterRejectsDuplicateRuleClassShortNames(): void
    {
        $validator = new ReflectionMethod(SodaInitFileEmitter::class, 'assertUniqueShortName');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('MaxLength');
        $this->expectExceptionMessage('Vendor\Structural\MaxLength');
        $this->expectExceptionMessage('Vendor\Complexity\MaxLength');

        $validator->invoke(null, [
            'MaxLength' => 'Vendor\Structural\MaxLength',
        ], 'MaxLength', 'Vendor\Complexity\MaxLength');
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
            $this->assertSame("<?php\nreturn static function (): void {};\n", file_get_contents($dir.'/soda.php'));
        } finally {
            chdir($cwd);
            unlink($dir.'/soda.php');
            rmdir($dir);
        }
    }
}
