<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\FactCollector;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Analysis\StronglyConnectedComponents;
use Cosmira\Soda\Rules\Architecture\NoDependencyCycles;
use PHPUnit\Framework\TestCase;

final class NoDependencyCyclesTest extends TestCase
{
    public function testCrossFileCycleHasEvidence(): void
    {
        $findings = $this->findings([
            '<?php namespace Sales; use Billing\\Invoice as Bill; class Order { function pay(Bill $bill) {} }',
            '<?php namespace Billing; class Invoice { function order(\\Sales\\Order $order) {} }',
        ]);
        self::assertCount(1, $findings);
        self::assertSame(2, $findings[0]->value);
        self::assertSame(0, $findings[0]->threshold);
        self::assertSame(1, $findings[0]->line);
        self::assertStringContainsString('billing, sales', $findings[0]->message);
    }

    public function testOneWayAndInternalDependenciesAreAllowed(): void
    {
        self::assertSame([], $this->findings([
            '<?php namespace Sales; class Order { function pay(\\Billing\\Invoice $bill) {} }',
            '<?php namespace Billing; class Invoice {}',
        ]));
        self::assertSame([], $this->findings(['<?php namespace Sales; class Order { function run(Item $item) {} } class Item { function run(Order $order) {} }']));
    }

    public function testConfiguredPrefixGroupsNestedNamespaces(): void
    {
        $sources = [
            '<?php namespace App\\Sales\\Orders; class Order { function run(\\App\\Sales\\Items\\Item $item) {} }',
            '<?php namespace App\\Sales\\Items; class Item { function run(\\App\\Sales\\Orders\\Order $order) {} }',
        ];
        self::assertCount(1, $this->findings($sources));
        self::assertSame([], $this->findings($sources, ['App\\Sales']));
        self::assertCount(1, $this->findings($sources, ['App\\Sales', 'App\\Sales\\Items']));
    }

    public function testComponentFinderHandlesLongCycleAndResetsBetweenProjects(): void
    {
        $finder = new StronglyConnectedComponents;
        self::assertSame([['a', 'b', 'c'], ['d', 'e']], $finder->collect(['a' => ['b'], 'b' => ['c'], 'c' => ['a'], 'd' => ['e'], 'e' => ['d']]));
        self::assertSame([['x']], $finder->collect(['x' => []]));
    }

    private function findings(array $sources, array $modules = []): array
    {
        $rule = new NoDependencyCycles($modules);
        $project = new ProjectFacts;
        foreach ($sources as $source) {
            $path = tempnam(sys_get_temp_dir(), 'soda-cycle-');
            file_put_contents($path, $source);

            try {
                $project->add((new FactCollector)->collect($path, $rule->requiredAnalyses()));
            } finally {
                unlink($path);
            }
        }

        return iterator_to_array($rule->checkProject($project));
    }
}
