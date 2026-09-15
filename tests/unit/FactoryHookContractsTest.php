<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\FactCollector;
use Cosmira\Soda\Rules\Structure\NoTrivialFactories;
use PHPUnit\Framework\TestCase;

final class FactoryHookContractsTest extends TestCase
{
    public function testObservedDispatchProtectsOnlyMatchingHooks(): void
    {
        $source = 'class Manager { function driver($driver) { $method = "create".ucfirst($driver)."Driver"; return $this->$method(); } }
            class Engines extends Manager { protected function createNullDriver() { return new Product(); } public function unrelated() { return new Product(); } }';
        $findings = $this->findings($source);
        self::assertCount(1, $findings);
        self::assertSame('unrelated', $findings[0]->method);
    }

    public function testNameAndDecorativeDispatchDoNotGrantExemptions(): void
    {
        foreach ([
            '$method = "create".ucfirst($driver)."Driver";',
            '$method = "create".ucfirst($driver)."Driver"; return $service->$method();',
            '$method = "create".ucfirst($driver)."Driver"; $method = "other"; return $this->$method();',
            '$method = $driver.$driver; return $this->$method();',
            '$closure = function () use ($driver) { $method = "create".$driver."Driver"; }; return $this->$method();',
        ] as $body) {
            self::assertCount(1, $this->findings('class Manager { function driver($driver) { '.$body.' } } class Engines extends Manager { protected function createNullDriver() { return new Product(); } }'), $body);
        }
    }

    public function testDeclaredParentMethodIsAContractButPrivateMethodIsNot(): void
    {
        self::assertSame([], $this->findings('abstract class Manager { abstract protected function build(); } class Engines extends Manager { protected function build() { return new Product(); } }'));
        self::assertCount(1, $this->findings('class Manager { private function build() {} } class Engines extends Manager { protected function build() { return new Product(); } }'));
    }

    public function testInstalledParentDispatchIsRecognizedWithoutExecutingIt(): void
    {
        $root = tempnam(sys_get_temp_dir(), 'soda-factory-hook-');
        unlink($root);
        mkdir($root.'/vendor/composer', recursive: true);
        mkdir($root.'/vendor/vendor/api/src', recursive: true);
        mkdir($root.'/src');
        $metadata = $root.'/vendor/composer/installed.json';
        $parent = $root.'/vendor/vendor/api/src/Manager.php';
        $child = $root.'/src/Engines.php';

        try {
            file_put_contents($metadata, json_encode(['packages' => [[
                'name' => 'vendor/api', 'install-path' => '../vendor/api', 'autoload' => ['psr-4' => ['Vendor\\Api\\' => 'src/']],
            ]]], JSON_THROW_ON_ERROR));
            file_put_contents($parent, '<?php namespace Vendor\Api; throw new \RuntimeException("must not execute"); class Manager { function driver($driver) { $method = "create".ucfirst($driver)."Driver"; return $this->$method(); } }');
            file_put_contents($child, '<?php class Engines extends \Vendor\Api\Manager { protected function createNullDriver() { return new Product(); } }');
            $rule = new NoTrivialFactories;
            self::assertSame([], iterator_to_array($rule->checkFile((new FactCollector)->collect($child, $rule->requiredAnalyses()))));
        } finally {
            foreach ([$metadata, $parent, $child] as $path) {
                unlink($path);
            }
            foreach (['vendor/vendor/api/src', 'vendor/vendor/api', 'vendor/vendor', 'vendor/composer', 'vendor', 'src', ''] as $directory) {
                rmdir($root.'/'.$directory);
            }
        }
    }

    private function findings(string $source): array
    {
        $path = tempnam(sys_get_temp_dir(), 'soda-hook-source-');
        file_put_contents($path, '<?php '.$source);

        try {
            $rule = new NoTrivialFactories;

            return iterator_to_array($rule->checkFile((new FactCollector)->collect($path, $rule->requiredAnalyses())));
        } finally {
            unlink($path);
        }
    }
}
