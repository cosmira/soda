<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use PHPUnit\Framework\TestCase;

final class OptionalMethodPhpDocTest extends TestCase
{
    private string $candidate;

    protected function setUp(): void
    {
        $this->candidate = dirname(__DIR__, 2).'/demo-app/candidates/53-self-documenting-action';
    }

    public function testStandardCatalogAllowsSelfDocumentingFrameworkActions(): void
    {
        $files = $this->candidateFiles();
        $result = Analyzer::analyze($files, dirname($this->candidate, 2).'/soda.php');

        self::assertSame([], $result->violations->all());
    }

    public function testTeamsCanExplicitlyOptIntoMultilineMethodPhpDoc(): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'soda-method-doc-config-');
        self::assertIsString($temporary);
        $config = $temporary.'.php';
        rename($temporary, $config);
        file_put_contents($config, <<<'PHP'
<?php

use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Documentation\MultilineMethodPhpDoc;

return Soda::configure()
    ->withPaths([__DIR__])
    ->with([
        new MultilineMethodPhpDoc(),
    ]);
PHP);

        try {
            $result = Analyzer::analyze($this->candidateFiles(), $config);
        } finally {
            unlink($config);
        }

        self::assertCount(3, $result->violations);
        self::assertSame(
            ['__invoke', 'handle', 'show'],
            $result->violations
                ->map(static fn ($violation): ?string => $violation->method)
                ->sort()
                ->values()
                ->all(),
        );
    }

    /** @return list<string> */
    private function candidateFiles(): array
    {
        $files = glob($this->candidate.'/*.php');
        self::assertIsArray($files);
        sort($files);

        return $files;
    }
}
