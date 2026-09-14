<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use PHPUnit\Framework\TestCase;

final class OptionalVariableNameWordsTest extends TestCase
{
    private string $candidate;

    protected function setUp(): void
    {
        $this->candidate = dirname(__DIR__, 2).'/demo-app/candidates/54-domain-variable-names';
    }

    public function testStandardCatalogAllowsClearDomainQualifiedVariables(): void
    {
        $result = Analyzer::analyze($this->candidateFiles(), dirname($this->candidate, 2).'/soda.php');

        self::assertSame([], $result->violations->all());
    }

    public function testTeamsCanExplicitlyOptIntoAWordCountPolicy(): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'soda-variable-words-config-');
        self::assertIsString($temporary);
        $config = $temporary.'.php';
        rename($temporary, $config);
        file_put_contents($config, <<<'PHP'
<?php

use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Naming\MaxVariableNameWords;

return Soda::configure()
    ->withPaths([__DIR__])
    ->with([
        new MaxVariableNameWords(maxWords: 1),
    ]);
PHP);

        try {
            $result = Analyzer::analyze($this->candidateFiles(), $config);
        } finally {
            unlink($config);
        }

        self::assertCount(8, $result->violations);
        self::assertSame(
            ['billingCycle', 'deliveryDate', 'deliveryZone', 'invoiceTotal', 'orderNumber', 'shippingAddress', 'tenantId', 'timeZone'],
            $result->violations
                ->map(fn ($violation): string => $this->variableName($violation->message))
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

    private function variableName(?string $message): string
    {
        self::assertIsString($message);
        preg_match('/Variable "\$(?<name>[^"]+)"/', $message, $matches);
        self::assertArrayHasKey('name', $matches);

        return $matches['name'];
    }
}
