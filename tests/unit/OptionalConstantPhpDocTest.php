<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Reporting\Violation;
use PHPUnit\Framework\TestCase;

final class OptionalConstantPhpDocTest extends TestCase
{
    private string $candidate;

    protected function setUp(): void
    {
        $this->candidate = dirname(__DIR__, 2).'/demo-app/candidates/59-self-documenting-constants';
    }

    public function testStandardCatalogAllowsSelfDocumentingTypedConstants(): void
    {
        $result = Analyzer::analyze($this->candidateFiles(), dirname($this->candidate, 2).'/soda.php');

        self::assertSame([], $result->violations->all());
    }

    public function testTeamsCanExplicitlyOptIntoMultilineConstantPhpDoc(): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'soda-constant-doc-config-');
        self::assertIsString($temporary);
        $config = $temporary.'.php';
        rename($temporary, $config);
        file_put_contents($config, <<<'PHP'
<?php

use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Documentation\MultilineConstantPhpDoc;

return Soda::configure()
    ->withPaths([__DIR__])
    ->with([
        new MultilineConstantPhpDoc(['public']),
    ]);
PHP);

        try {
            $result = Analyzer::analyze($this->candidateFiles(), $config);
        } finally {
            unlink($config);
        }

        self::assertCount(3, $result->violations);
        self::assertSame(
            [
                'Public constant::DECIMALS must have a multiline PHPDoc comment',
                'Public constant::EMAIL must have a multiline PHPDoc comment',
                'Public constant::PAID must have a multiline PHPDoc comment',
            ],
            $result->violations
                ->map(static fn (Violation $violation): string => $violation->message)
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
