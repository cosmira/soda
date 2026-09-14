<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Config\ConfigException;
use Cosmira\Soda\Config\ConfigLoader;
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Structure\MaxFileLoc;
use Cosmira\Soda\Rules\Structure\MaxMethodLength;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SodaConfigTest extends TestCase
{
    public function testWithAcceptsRuleInstances(): void
    {
        $soda = Soda::configure()
            ->with([
                new MaxMethodLength(80),
                new MaxFileLoc(500),
            ]);

        $checkers = $soda->checks();

        $this->assertCount(2, $checkers);
        $this->assertInstanceOf(MaxMethodLength::class, $checkers[0]);
        $this->assertInstanceOf(MaxFileLoc::class, $checkers[1]);
    }

    public function testWithIsChainable(): void
    {
        $soda = Soda::configure()
            ->with([new MaxFileLoc(300)])
            ->with([new MaxMethodLength(100)]);

        $this->assertCount(2, $soda->checks());
    }

    public function testStandardRulesDeclaresEveryEnabledRuleId(): void
    {
        $soda = Soda::configure()->with(RuleCatalog::standard());

        $this->assertSame(array_keys(RuleCatalog::standardDefinitions()), array_map(fn ($check) => $check->id(), $soda->checks()));
    }

    public function testWithRejectsInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Soda::configure()->with([new \stdClass]);
    }

    public function testCustomArrayPreservesInstancesAndRegistrationOrder(): void
    {
        $first = new MaxFileLoc(300);
        $second = new MaxMethodLength(70);
        $config = Soda::configure();

        $this->assertSame($config, $config->with([$first, $second])->with([$first]));
        $this->assertSame([$first, $second, $first], $config->checks());
    }

    public function testEmptyRuleSetKeepsConfigurationEmpty(): void
    {
        $this->assertSame([], Soda::configure()->with([])->checks());
    }

    public function testWithPathsRegistersAnalysisPaths(): void
    {
        $soda = Soda::configure()->withPaths(['src/', 'tests/']);

        $this->assertSame(['src/', 'tests/'], $soda->paths());
    }

    public function testFromPhpConfiguratorFileLoadsInstance(): void
    {
        $path = sys_get_temp_dir().'/soda-php-cfg-'.uniqid().'.php';
        file_put_contents($path, <<<'PHP'
<?php
declare(strict_types=1);
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Structure\MaxMethodLength;
return Soda::configure()
    ->withPaths(['src/'])
    ->with([new MaxMethodLength(77)]);
PHP);

        try {
            $qc = (new ConfigLoader())->load($path);
            $this->assertCount(1, $qc->checks());
            $this->assertInstanceOf(MaxMethodLength::class, $qc->checks()[0]);
            $this->assertSame(['src/'], $qc->paths());
        } finally {
            unlink($path);
        }
    }

    public function testFromPhpConfiguratorFileRejectsNonCallable(): void
    {
        $path = sys_get_temp_dir().'/soda-php-bad-'.uniqid().'.php';
        file_put_contents($path, "<?php\nreturn [];\n");

        try {
            $this->expectException(ConfigException::class);
            (new ConfigLoader())->load($path);
        } finally {
            unlink($path);
        }
    }
}
