<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Rules\Architecture\NoRepeatedTypeDispatch;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class NoRepeatedTypeDispatchTest extends TestCase
{
    public function testRepeatedInstanceofFamiliesHaveMethodEvidence(): void
    {
        $source = '<?php namespace App; class Handler {
            function print($item) { if ($item instanceof A) {} if ($item instanceof B) {} }
            function save($other) { if ($other instanceof B) {} if ($other instanceof A) {} }
        }';
        $findings = $this->findings($source);
        self::assertCount(1, $findings);
        self::assertSame(2, $findings[0]->value);
        self::assertSame(1, $findings[0]->threshold);
        self::assertSame(2, $findings[0]->line);
        self::assertSame('App\\Handler', $findings[0]->class);
        self::assertStringContainsString('print, save', $findings[0]->message);
    }

    public function testEquivalentClassSelectorsAndAliases(): void
    {
        $source = '<?php use Domain\\First as A; use Domain\\Second as B; class Handler {
            function print($item) { return match ($item::class) { A::class => 1, B::class => 2 }; }
            function save($item) { switch (get_class($item)) { case A::class: break; case B::class: break; } }
        }';
        self::assertCount(1, $this->findings($source));
        self::assertSame([], $this->findings($source, 3));
    }

    public function testRepeatedScalarDiscriminatorFamilies(): void
    {
        self::assertCount(1, $this->findings('<?php class Handler {
            function print($item) { return match ($item->kind) { "pdf" => printPdf(), "csv" => printCsv() }; }
            function save($item) { return match ($item->kind) { "csv" => saveCsv(), "pdf" => savePdf() }; }
        }'));
    }

    public function testDifferentFamiliesAndDifferentClassesRemainSeparate(): void
    {
        self::assertSame([], $this->findings('<?php class Handler {
            function first($a) { return $a instanceof A || $a instanceof B; }
            function second($a) { return $a instanceof A || $a instanceof C; }
        }'));
        self::assertSame([], $this->findings('<?php class One { function run($a) { return $a instanceof A || $a instanceof B; } }
            class Two { function run($a) { return $a instanceof A || $a instanceof B; } }'));
    }

    public function testOneMethodCannotSupplyTwoVotes(): void
    {
        self::assertSame([], $this->findings('<?php class Handler {
            function run($a, $b) { return ($a instanceof A || $a instanceof B) && ($b instanceof A || $b instanceof B); }
        }'));
    }

    public function testNestedCallablesDoNotContributeToTheirOwner(): void
    {
        self::assertSame([], $this->findings('<?php class Handler {
            function run($a) { return $a instanceof A || $a instanceof B; }
            function later() { return function($a) { return $a instanceof A || $a instanceof B; }; }
        }'));
    }

    public function testThresholdMustRequireRepetition(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new NoRepeatedTypeDispatch(1);
    }

    public function testAliasesAndEqualityDiscriminators(): void
    {
        self::assertCount(1, $this->findings('<?php class Handler {
            function first($item) { $alias = $item; if ($alias instanceof A) {} if ($item instanceof B) {} }
            function second($item) { return $item instanceof A || $item instanceof B; }
        }'));
        self::assertCount(1, $this->findings('<?php class Handler {
            function first($item) { if ($item->kind === "a") {} elseif ($item->kind === "b") {} }
            function second($item) { return match($item->kind) { "a" => 1, "b" => 2 }; }
        }'));
    }

    private function findings(string $source, int $minimum = 2): array
    {
        $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse($source);
        $nodes = (new NodeTraverser(new NameResolver))->traverse($nodes);

        return iterator_to_array((new NoRepeatedTypeDispatch($minimum))->checkFile(new FileFacts('/project/types.php', $source, $nodes, [])));
    }
}
