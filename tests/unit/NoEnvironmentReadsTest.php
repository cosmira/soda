<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Rules\Architecture\NoEnvironmentReads;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NoEnvironmentReadsTest extends TestCase
{
    use ParsesPhpSnippets;

    #[DataProvider('configurationPaths')]
    public function testExcludesOnlyConfiguredBoundaries(string $path, int $expected): void
    {
        $rule = new NoEnvironmentReads(ignorePathPrefixes: ['/project/config', '/project/packages/Octopus/config']);
        $file = new FileFacts($path, '', $this->parseSnippet('return env("TOKEN");'), []);

        $this->assertCount($expected, iterator_to_array($rule->checkFile($file)));
    }

    public static function configurationPaths(): \Iterator
    {
        yield 'application config' => ['/project/config/app.php', 0];
        yield 'nested config' => ['/project/config/services/cache.php', 0];
        yield 'package config' => ['/project/packages/Octopus/config/octopus.php', 0];
        yield 'application code' => ['/project/app/Service.php', 1];
        yield 'package code' => ['/project/packages/Octopus/Client.php', 1];
        yield 'similar directory name' => ['/project/configuration/app.php', 1];
        yield 'other package config' => ['/project/packages/Other/config/app.php', 1];
    }

    public function testDefaultPolicyStillReportsEnvironmentAccessInConfig(): void
    {
        $file = new FileFacts('/project/config/app.php', '', $this->parseSnippet('return env("TOKEN");'), []);

        $this->assertCount(1, iterator_to_array((new NoEnvironmentReads)->checkFile($file)));
    }
}
