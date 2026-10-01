<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Usage\NoTemporaryReturnVariable;
use PHPUnit\Framework\TestCase;

final class NoTemporaryReturnVariableTest extends TestCase
{
    use ParsesPhpSnippets;

    public function testReportsImmediateReturnIncludingPhpDocAndNestedBodies(): void
    {
        foreach ([
            'class Api { function version(): int { /** @var int $result */ $result = $this->invoke("GetVersion"); return $result; } }',
            'function run() { $result = 42; return $result; }',
            'function run($ready) { if ($ready) { $result = load(); return $result; } return null; }',
            '$fn = function () { $result = load(); return $result; };',
            'function outer() { return function () { $result = load(); return $result; }; }',
        ] as $source) {
            $violations = $this->violations($source);
            self::assertCount(1, $violations, $source);
            self::assertSame('no_temporary_return_variable', $violations[0]->rule);
            self::assertSame(1, $violations[0]->line);
        }
    }

    public function testPreservesVariablesWithOtherUsesAndReferenceSemantics(): void
    {
        foreach ([
            'function run() { return load(); }',
            'function run() { $result = load(); logValue($result); return $result; }',
            'function run() { $result = load(); return $result + 1; }',
            'function run($result) { $result = load(); return $result; }',
            'function run() { $result =& load(); return $result; }',
            'function &run() { $result = load(); return $result; }',
            'function run() { global $result; $result = load(); return $result; }',
            'function run() { static $result; $result = load(); return $result; }',
            'function run() { try { $result = load(); return $result; } finally { logValue($result); } }',
            'function run() { $result = load(); return function () use ($result) { return $result; }; }',
            'function run() { $result = $result + 1; return $result; }',
            'function run() { $result = load(); return $other; }',
        ] as $source) {
            self::assertSame([], $this->violations($source), $source);
        }
    }

    /** @return list<Violation> */
    private function violations(string $source): array
    {
        $facts = new FileFacts('/example.php', '<?php '.$source, $this->parseSnippet($source), [
            'file_loc' => 1, 'classes_count' => 0, 'classes' => [], 'methods' => [], 'namespaces' => [], 'interfaceParents' => [],
        ]);

        return iterator_to_array((new NoTemporaryReturnVariable)->checkFile($facts));
    }
}
