<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\SingleVisitorTraversal;
use Cosmira\Soda\Rules\Naming\CompoundVariableNameVisitor;
use Cosmira\Soda\Tests\ParsesPhpSnippets;
use PHPUnit\Framework\TestCase;

final class CompoundVariableNameVisitorTest extends TestCase
{
    use ParsesPhpSnippets;

    public function testCollectsEachCompoundLocalAndParameterOnce(): void
    {
        $visitor = new CompoundVariableNameVisitor;
        SingleVisitorTraversal::traverse($this->parsePhpFile(<<<'PHP'
<?php
final class Thermometer
{
    public function __construct(public float $valueInCelsius) {}

    public function display(float $temperatureInFahrenheit): void
    {
        $fileName = 'temperature.txt';
        echo $fileName;
        echo $fileName;
        $source_file_name = $fileName;
        $HTTP = 'ok';
        echo $this->valueInCelsius, $_SERVER['APP_ENV'];
    }
}
PHP), $visitor);

        $occurrences = $visitor->occurrences();

        $this->assertSame(
            ['temperatureInFahrenheit', 'fileName', 'source_file_name'],
            array_map(static fn ($item): string => $item->name, $occurrences),
        );
        $this->assertSame([3, 2, 3], array_map(static fn ($item): int => $item->wordCount(), $occurrences));
        $this->assertSame('Thermometer', $occurrences[0]->class);
        $this->assertSame('display', $occurrences[0]->method);
    }

    public function testUsesSeparateLexicalScopesForSameVariableName(): void
    {
        $visitor = new CompoundVariableNameVisitor;
        SingleVisitorTraversal::traverse($this->parsePhpFile(<<<'PHP'
<?php
function first(): void { $fileName = 'first'; }
function second(): void { $fileName = 'second'; }
PHP), $visitor);

        $this->assertCount(2, $visitor->occurrences());
        $this->assertSame(['first', 'second'], array_map(
            static fn ($item): ?string => $item->method,
            $visitor->occurrences(),
        ));
    }
}
