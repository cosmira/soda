<?php

declare(strict_types=1);

namespace Cosmira\Soda\Commands;

use Cosmira\Soda\Application;
use Cosmira\Soda\Config\RuleCatalog;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\OutputStyle;
use Illuminate\Events\Dispatcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ListRulesCommandTest extends TestCase
{
    public function testListRulesShowsEveryCatalogRule(): void
    {
        $container = new Application();
        $artisan = new ConsoleApplication($container, new Dispatcher($container), '8.0');
        $artisan->setAutoExit(false);
        $artisan->add(new ListRulesCommand());

        $input = new ArrayInput(['command' => 'list:rules']);
        $output = new BufferedOutput();

        $exitCode = $artisan->run($input, new OutputStyle($input, $output));
        $content = $output->fetch();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Rule id', $content);
        $this->assertStringContainsString('Severity', $content);
        $this->assertStringContainsString('Default', $content);
        $this->assertStringContainsString('no_assignment_in_condition', $content);

        foreach (RuleCatalog::definitions() as $id => $definition) {
            $this->assertStringContainsString($id, $content, $id);
            $this->assertStringContainsString($definition['severity'], $content, $id);
            $this->assertStringContainsString((string) $definition['default'], $content, $id);
        }

        $this->assertRulesFollowCatalogOrder($content);
    }

    private function assertRulesFollowCatalogOrder(string $content): void
    {
        $lastPosition = -1;

        foreach (array_keys(RuleCatalog::definitions()) as $id) {
            $position = strpos($content, '| '.$id);

            $this->assertIsInt($position, $id);
            $this->assertGreaterThan($lastPosition, $position, $id);

            $lastPosition = $position;
        }
    }
}
