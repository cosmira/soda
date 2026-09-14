<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Reporting\Violation;
use PHPUnit\Framework\TestCase;

final class OptionalPropertyPhpDocTest extends TestCase
{
    private string $candidate;

    protected function setUp(): void
    {
        $this->candidate = dirname(__DIR__, 2).'/demo-app/candidates/58-readonly-dto-properties';
    }

    public function testStandardCatalogAllowsSelfDocumentingReadonlyProperties(): void
    {
        $result = Analyzer::analyze($this->candidateFiles(), dirname($this->candidate, 2).'/soda.php');

        self::assertSame([], $result->violations->all());
    }

    public function testTeamsCanExplicitlyOptIntoMultilinePropertyPhpDoc(): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'soda-property-doc-config-');
        self::assertIsString($temporary);
        $config = $temporary.'.php';
        rename($temporary, $config);
        file_put_contents($config, <<<'PHP'
<?php

use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Documentation\MultilinePropertyPhpDoc;

return Soda::configure()
    ->withPaths([__DIR__])
    ->with([
        new MultilinePropertyPhpDoc(['public']),
    ]);
PHP);

        try {
            $result = Analyzer::analyze($this->candidateFiles(), $config);
        } finally {
            unlink($config);
        }

        self::assertCount(6, $result->violations);
        self::assertSame(
            [
                'Public property::amount must have a multiline PHPDoc comment',
                'Public property::closesAt must have a multiline PHPDoc comment',
                'Public property::currency must have a multiline PHPDoc comment',
                'Public property::environment must have a multiline PHPDoc comment',
                'Public property::opensAt must have a multiline PHPDoc comment',
                'Public property::release must have a multiline PHPDoc comment',
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
