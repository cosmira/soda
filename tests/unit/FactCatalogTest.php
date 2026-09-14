<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\FactCatalog;
use Cosmira\Soda\Analysis\FactCollector;
use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Application;
use Cosmira\Soda\Commands\ListMetricsCommand;
use Cosmira\Soda\Config\RuleExpression;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\ExpressionCheck;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Events\Dispatcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class FactCatalogTest extends TestCase
{
    public function testEveryDocumentedFactHasACollectorAndCompilesInItsScope(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'soda-facts-');
        file_put_contents($path, '<?php namespace Demo; class Example { public function run(int $value): int { if ($value) { return 1; } return 0; } }');

        try {
            foreach (FactCatalog::all() as $scope => $fields) {
                foreach ($fields as $name => $field) {
                    self::assertNotSame('', $field['description']);
                    self::assertNotSame('', $field['unit']);
                    [$class, $method] = explode('::', $field['source']);
                    self::assertTrue(method_exists($class, $method), $field['source']);
                    $condition = $field['type'] === 'number' ? $name.' >= 0' : $name.' matches "*"';
                    $expression = new RuleExpression($scope.' where '.$condition);
                    $required = $field['analysis'] === null ? [] : [$field['analysis']];
                    self::assertSame($required, $expression->requiredAnalyses());
                    $full = $this->resolveScope((new FactCollector)->collect($path), $scope);
                    $selective = $this->resolveScope((new FactCollector)->collect($path, $required), $scope);
                    self::assertSame($this->values($full, $scope, $name), $this->values($selective, $scope, $name));
                    foreach ($selective->rows($scope) as $row) {
                        self::assertIsBool($expression->isMatch($row));
                    }
                }
            }
        } finally {
            unlink($path);
        }
    }

    public function testMetricCommandUsesTheSchemaAndRejectsUnknownScopes(): void
    {
        $container = new Application;
        $application = new ConsoleApplication($container, new Dispatcher($container), 'test');
        $application->setAutoExit(false);
        $application->add(new ListMetricsCommand);
        $output = new BufferedOutput;
        self::assertSame(0, $application->run(new ArrayInput(['command' => 'list:metrics', 'scope' => 'method']), $output));
        $text = $output->fetch();
        foreach (FactCatalog::all()['method'] as $name => $field) {
            self::assertStringContainsString($name, $text);
            self::assertStringContainsString($field['source'], $text);
            self::assertStringContainsString($field['description'], $text);
        }
        self::assertSame(1, $application->run(new ArrayInput(['command' => 'list:metrics', 'scope' => 'missing']), $output));
        self::assertStringContainsString('Available scopes: file, class, method.', $output->fetch());
    }

    public function testExpressionCompilesOncePerInstanceAndKeepsMeasuredValues(): void
    {
        $rule = new class extends ExpressionCheck
        {
            public int $compilations = 0;

            public function id(): string
            {
                return 'arguments';
            }

            protected function expression(): string
            {
                $this->compilations++;

                return 'method where args > 2';
            }

            protected function violation(FileFacts $file, array $row): Violation
            {
                return new Violation(rule: $this->id(), file: $file->path, value: $row['args'], threshold: 2, method: $row['name']);
            }
        };
        $file = new FileFacts('/test.php', '', [], ['methods' => ['Example::run' => ['args' => 4]]]);
        $rule->requiredAnalyses();
        $first = iterator_to_array($rule->checkFile($file));
        $second = iterator_to_array($rule->checkFile($file));
        iterator_to_array($rule->checkProject(new ProjectFacts));
        self::assertSame(1, $rule->compilations);
        self::assertEquals($first, $second);
        self::assertSame(4, $first[0]->value);
        self::assertSame(2, $first[0]->threshold);
    }

    private function resolveScope(FileFacts $file, string $scope): FileFacts
    {
        if ($scope !== 'class') {
            return $file;
        }
        $project = new ProjectFacts;
        $project->add($file);
        $project->resolveClasses();

        return new FileFacts($file->path, '', [], $project->files[$file->path]);
    }

    private function values(FileFacts $file, string $scope, string $name): array
    {
        $values = [];
        foreach ($file->rows($scope) as $row) {
            $values[] = $row[$name];
        }

        return $values;
    }
}
