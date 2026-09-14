<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\Runner as QualityAnalyser;
use Cosmira\Soda\Rules\Naming\CompoundVariableNameOccurrence;
use Cosmira\Soda\Rules\Naming\MaxVariableNameWords;
use Cosmira\Soda\Tests\CheckFixture;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MaxVariableNameWordsTest extends TestCase
{
    public function testReportsEveryNameAboveConfiguredWordLimit(): void
    {
        $violations = CheckFixture::forRule(new MaxVariableNameWords, $this->context([
            $this->occurrence('fileName', ['file', 'name'], 8),
            $this->occurrence('temperatureInFahrenheit', ['temperature', 'in', 'fahrenheit'], 9),
        ]));

        $this->assertCount(2, $violations);
        $this->assertSame([2, 3], $violations->map(static fn ($item): int => ['value' => $item->value, 'threshold' => $item->threshold]['value'])->all());
        $this->assertSame([1, 1], $violations->map(static fn ($item): int => ['value' => $item->value, 'threshold' => $item->threshold]['threshold'])->all());
        $this->assertSame('Thermometer', $violations[0]->class);
        $this->assertSame('display', $violations[0]->method);
        $this->assertStringContainsString('introduce a value object', (string) $violations[1]->message);
    }

    public function testSupportsLenientWordLimit(): void
    {
        $violations = CheckFixture::forRule(new MaxVariableNameWords(maxWords: 2), $this->context([
            $this->occurrence('fileName', ['file', 'name'], 8),
            $this->occurrence('temperatureInFahrenheit', ['temperature', 'in', 'fahrenheit'], 9),
        ]));

        $this->assertCount(1, $violations);
        $this->assertSame(9, $violations[0]->line);
    }

    public function testSupportsExplicitProperNameAndImplementationExceptions(): void
    {
        $violations = CheckFixture::forRule(new MaxVariableNameWords(ignore: ['$openAI', 'redisCache']), $this->context([
            $this->occurrence('openAI', ['open', 'ai'], 4),
            $this->occurrence('redisCache', ['redis', 'cache'], 5),
        ]));

        $this->assertCount(0, $violations);
    }

    public function testRejectsMeaninglessZeroWordLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MaxVariableNameWords(maxWords: 0);
    }

    public function testProductionPipelineFindsCompoundScalarsButAllowsValueObjectStateAndClassName(): void
    {
        $directory = sys_get_temp_dir().'/soda-variable-words-'.uniqid();
        mkdir($directory, 0700, true);
        $sourcePath = $directory.'/Temperature.php';
        $configPath = $directory.'/soda.php';

        file_put_contents($sourcePath, <<<'PHP'
<?php
namespace Domain\Weather;

final class FahrenheitTemperatureImplementation
{
    private function __construct(public float $valueInCelsius) {}

    public static function fromFahrenheit(float $degrees): self
    {
        $temperatureInFahrenheit = $degrees;
        $celsius = ($temperatureInFahrenheit - 32) * 5 / 9;

        return new self($celsius);
    }
}
PHP);
        file_put_contents($configPath, <<<'PHP'
<?php
return \Cosmira\Soda\Config\Soda::configure()->with([
    new \Cosmira\Soda\Rules\Naming\MaxVariableNameWords(),
]);
PHP);

        try {
            $result = (new QualityAnalyser)->analyse([$sourcePath], $configPath);

            $this->assertCount(1, $result->violations);
            $violation = $result->violations->first();
            $this->assertNotNull($violation);
            $this->assertSame('max_variable_name_words', $violation->rule);
            $this->assertSame('Domain\Weather\FahrenheitTemperatureImplementation', $violation->class);
            $this->assertSame('fromFahrenheit', $violation->method);
            $this->assertStringContainsString('$temperatureInFahrenheit', (string) $violation->message);
        } finally {
            unlink($sourcePath);
            unlink($configPath);
            rmdir($directory);
        }
    }

    /** @param list<CompoundVariableNameOccurrence> $occurrences */
    private function context(array $occurrences): array
    {
        $metrics = ['/project/Temperature.php' => ['compoundVariableNames' => $occurrences]];
        $core = $metrics;

        return [CheckFixture::checks(), $core];
    }

    /** @param list<non-empty-string> $words */
    private function occurrence(string $name, array $words, int $line): CompoundVariableNameOccurrence
    {
        return new CompoundVariableNameOccurrence($name, $words, $line, 'Thermometer', 'display');
    }
}
