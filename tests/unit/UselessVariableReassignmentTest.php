<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Rules\Usage\UselessVariableAnalyser;
use Cosmira\Soda\Rules\Usage\UselessVariableRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UselessVariableReassignmentTest extends TestCase
{
    use ParsesPhpSnippets;

    public function testReportsTheConditionalCollectionCopyAtItsAssignment(): void
    {
        $nodes = $this->parseSnippet(<<<'PHP'
class Handler {
    public function handle(?string $serverId): Collection {
        $servers = $this->systemCheck
            ->nodes()
            ->unique(static fn (DiscoveredService $server): string => serialize($server));
        $selected = $servers;
        if ($serverId !== null) {
            $selected = $servers->filter(
                static fn (DiscoveredService $server): bool
                    => ServerEndpointId::fromServer($server) === $serverId,
            );
            abort_if($selected->isEmpty(), 404, 'Diagnostic server not found.');
        }
        return $selected;
    }
}
PHP);
        $findings = (new UselessVariableAnalyser)->analyse($nodes);
        self::assertSame([['line' => 6, 'variable' => '$selected', 'source' => '$servers']], $findings);
        $violations = iterator_to_array((new UselessVariableRule)->checkFile(new FileFacts('/handler.php', '', $nodes, [])));
        self::assertCount(1, $violations);
        self::assertSame('useless_variable', $violations[0]->rule);
        self::assertSame(6, $violations[0]->line);
    }

    public function testAllowsUsingOneVariableForTheConditionalTransformation(): void
    {
        self::assertSame([], $this->findings('function handle($serverId) { $servers = nodes(); if ($serverId !== null) { $servers = $servers->filter($serverId); abort_if($servers->isEmpty(), 404); } return $servers; }'));
    }

    #[DataProvider('preservedCopies')]
    public function testPreservesIndependentOriginalValues(string $source): void
    {
        self::assertSame([], $this->findings($source));
    }

    public static function preservedCopies(): array
    {
        return [
            'original used after filtering'          => ['function run($servers, $condition) { $selected = $servers; if ($condition) { $selected = $servers->filter(); } return [$servers, $selected]; }'],
            'original used in another branch'        => ['function run($servers, $condition) { $selected = $servers; if ($condition) { $selected = $servers->filter(); } else { consume($servers); } return $selected; }'],
            'array copy modified'                    => ['function run($servers) { $selected = $servers; unset($selected[0]); return $selected; }'],
            'loop keeps original for each iteration' => ['function run($servers, $conditions) { $selected = $servers; foreach ($conditions as $condition) { $selected = $servers->filter($condition); consume($selected); } return $selected; }'],
            'two transformations of original'        => ['function run($servers, $a, $b) { $selected = $servers; if ($a) { $selected = $servers->filter($a); } if ($b) { $selected = $servers->filter($b); } return $selected; }'],
            'source changed'                         => ['function run($servers, $condition) { $selected = $servers; if ($condition) { $selected = $servers->filter(); } $servers = nodes(); return $selected; }'],
            'reference parameter'                    => ['function run(&$servers, $condition) { $selected = $servers; if ($condition) { $selected = $servers->filter(); } return $selected; }'],
            'source reference created earlier'       => ['function run($servers, $condition) { $original =& $servers; $selected = $servers; if ($condition) { $selected = $servers->filter(); } return $selected; }'],
            'previous closure retains source'        => ['function run($servers, $condition) { register(function () use ($servers) { return $servers; }); $selected = $servers; if ($condition) { $selected = $servers->filter(); } return $selected; }'],
            'previous arrow retains source'          => ['function run($servers, $condition) { register(fn () => $servers); $selected = $servers; if ($condition) { $selected = $servers->filter(); } return $selected; }'],
            'runtime this binding'                   => ['class Handler { function run($condition) { $selected = $this; if ($condition) { $selected = $this->filter(); } return $selected; } }'],
            'source aliases an array element'        => ['function run($rows, $condition) { foreach ($rows as &$servers) {} $selected = $servers; if ($condition) { $selected = $servers->filter(); } return $selected; }'],
            'reference return'                       => ['function &run($servers, $condition) { $selected = $servers; if ($condition) { $selected = $servers->filter(); } return $selected; }'],
            'global source'                          => ['function run($condition) { global $servers; $selected = $servers; if ($condition) { $selected = $servers->filter(); } return $selected; }'],
            'implicit source read'                   => ['function run($servers, $condition) { $selected = $servers; if ($condition) { $selected = $servers->filter(); } return compact("servers", "selected"); }'],
            'reassignment unrelated to source'       => ['function run($servers) { $selected = $servers; $selected = 10; return $selected; }'],
        ];
    }

    private function findings(string $source): array
    {
        return (new UselessVariableAnalyser)->analyse($this->parseSnippet($source));
    }
}
