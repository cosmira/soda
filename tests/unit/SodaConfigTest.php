<?php

declare(strict_types=1);

namespace Bunnivo\Soda;

use Bunnivo\Soda\Config\Soda;
use Bunnivo\Soda\Plugins\Rules\Structural\MaxFileLoc;
use Bunnivo\Soda\Plugins\Rules\Structural\MaxMethodLength;
use Bunnivo\Soda\Quality\ConfigException;
use Bunnivo\Soda\Quality\QualityConfig;
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

        $checkers = $soda->pluginCheckers();

        $this->assertCount(2, $checkers);
        $this->assertInstanceOf(MaxMethodLength::class, $checkers[0]);
        $this->assertInstanceOf(MaxFileLoc::class, $checkers[1]);
    }

    public function testWithIsChainable(): void
    {
        $soda = Soda::configure()
            ->with([new MaxFileLoc(300)])
            ->with([new MaxMethodLength(100)]);

        $this->assertCount(2, $soda->pluginCheckers());
    }

    public function testWithRejectsInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Soda::configure()->with([new \stdClass]);
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
use Bunnivo\Soda\Config\Soda;
use Bunnivo\Soda\Plugins\Rules\Structural\MaxMethodLength;
return Soda::configure()
    ->withPaths(['src/'])
    ->with([new MaxMethodLength(77)]);
PHP);

        try {
            $qc = QualityConfig::fromPhpConfiguratorFile($path);
            $this->assertCount(1, $qc->pluginCheckers);
            $this->assertInstanceOf(MaxMethodLength::class, $qc->pluginCheckers[0]);
            $this->assertSame(['src/'], $qc->paths);
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
            QualityConfig::fromPhpConfiguratorFile($path);
        } finally {
            unlink($path);
        }
    }
}
