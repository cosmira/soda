<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\FactCollector;
use PHPUnit\Framework\TestCase;

final class DocumentationExamplesTest extends TestCase
{
    private const EXAMPLE_NAMESPACE = 'Cosmira\\Soda\\Tests\\DocumentationExamples';

    public function testPricingExamplesPreserveEveryChargeAndInputRead(): void
    {
        $fixture = $this->valueObject([]);
        class_alias($fixture::class, self::EXAMPLE_NAMESPACE.'\\Order');
        class_alias($fixture::class, self::EXAMPLE_NAMESPACE.'\\OrderItem');
        $examples = $this->examples('max_cyclomatic_complexity', 'OrderPricer');
        $charges = ['fragile' => 5, 'oversized' => 15, 'hazmat' => 30, 'perishable' => 20, 'requiresSignature' => 12];
        $cases = [[[], 0], [array_fill_keys(array_keys($charges), false), 7], [array_fill_keys(array_keys($charges), true), 89]];
        foreach ($charges as $flag => $charge) {
            $cases[] = [[$flag => true], $charge + 7];
        }

        foreach ([false, true] as $priority) {
            foreach ([false, true] as $international) {
                foreach ($cases as [$flags, $expectedItemCharge]) {
                    foreach ($examples as $example) {
                        $items = $flags === [] ? [] : [$this->valueObject([...array_fill_keys(array_keys($charges), false), ...$flags, 'tax' => 7])];
                        $order = $this->valueObject(['total' => 100, 'isPriority' => $priority, 'isInternational' => $international, 'items' => $items]);
                        self::assertSame(100 + ($priority ? 10 : 0) + ($international ? 25 : 0) + $expectedItemCharge, $example->price($order));
                        self::assertSame(['total', 'isPriority', 'isInternational', 'items'], $order->calls);
                        foreach ($items as $item) {
                            self::assertSame([...array_keys($charges), 'tax'], $item->calls);
                        }
                    }
                }
            }
        }
        foreach ($examples as $example) {
            $items = [
                $this->valueObject([...array_fill_keys(array_keys($charges), true), 'tax' => 7]),
                $this->valueObject([...array_fill_keys(array_keys($charges), false), 'tax' => 7]),
            ];
            $order = $this->valueObject(['total' => 100, 'isPriority' => false, 'isInternational' => false, 'items' => $items]);
            self::assertSame(196, $example->price($order));
        }
    }

    public function testCognitiveExamplesPreservePassCountAndShortCircuitEffects(): void
    {
        $fixture = $this->valueObject([]);
        class_alias($fixture::class, self::EXAMPLE_NAMESPACE.'\\Item');
        $examples = $this->examples('max_cognitive_complexity', 'BatchRunner');
        $cases = [
            [['ready' => false], ['ready']],
            [['valid' => false], ['ready', 'valid']],
            [['priority' => false], ['ready', 'valid', 'priority']],
            [['fresh' => false], ['ready', 'valid', 'priority', 'fresh']],
            [[], ['ready', 'valid', 'priority', 'fresh', 'process']],
            [[], ['ready', 'valid', 'priority', 'fresh', 'process']],
        ];

        foreach ($examples as $example) {
            $items = [];
            $processed = [];
            foreach ($cases as $index => [$flags]) {
                $items[] = $this->valueObject([
                    ...array_fill_keys(['ready', 'valid', 'priority', 'fresh'], true),
                    ...$flags,
                    'process' => static function () use ($index, &$processed): void {
                        $processed[] = $index;
                    },
                ]);
            }
            $example->run($items);
            self::assertSame([4, 5, 4, 5, 4, 5], $processed);
            foreach ($cases as $index => [, $pass]) {
                self::assertSame([...$pass, ...$pass, ...$pass], $items[$index]->calls);
            }
            $example->run([]);
        }
    }

    public function testComplexityScoresMatchTheDocumentedExamples(): void
    {
        foreach ([
            ['max_cyclomatic_complexity', 'complexity', [9], [4, 6]],
            ['max_cognitive_complexity', 'cognitive_complexity', [16], [3, 4]],
        ] as [$rule, $metric, $before, $after]) {
            foreach ($this->sourceExamples($rule) as $index => $source) {
                $path = tempnam(sys_get_temp_dir(), 'soda-doc-example-');

                try {
                    file_put_contents($path, "<?php\n".$source);
                    $facts = (new FactCollector)->collect($path);
                    self::assertSame($index === 0 ? $before : $after, array_column($facts->metrics['methods'], $metric));
                } finally {
                    unlink($path);
                }
            }
        }
    }

    private function examples(string $rule, string $class): array
    {
        return array_map(
            static fn (string $source): object => eval('namespace '.self::EXAMPLE_NAMESPACE.'; '.str_replace('final class '.$class, 'return new class', $source).';'),
            $this->sourceExamples($rule),
        );
    }

    private function sourceExamples(string $rule): array
    {
        $source = file_get_contents(__DIR__.'/../../docs/rules/'.$rule.'.md');
        preg_match_all('/```php\n(.*?)\n```/s', $source, $matches);

        $examples = array_slice($matches[1], 0, 2);
        self::assertCount(2, $examples);

        return $examples;
    }

    private function valueObject(array $values): object
    {
        return new class($values)
        {
            public array $calls = [];

            public function __construct(private array $values) {}

            public function __call(string $name, array $arguments): mixed
            {
                $this->calls[] = $name;

                $value = $this->values[$name];

                return $value instanceof \Closure ? $value() : $value;
            }
        };
    }
}
