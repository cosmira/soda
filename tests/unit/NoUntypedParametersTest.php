<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Rules\Architecture\NoUntypedParameters;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class NoUntypedParametersTest extends TestCase
{
    public function testEveryCallableAndContractRequiresTypes(): void
    {
        $source = '<?php
            interface Contract { function run($input); }
            class Worker { function run($input = null) {} }
            function work(&...$values) {}
            $closure = function ($input) {};
            $arrow = fn ($input) => $input;
        ';
        $findings = $this->findings($source);
        self::assertCount(5, $findings);
        self::assertSame([2, 3, 4, 5, 6], array_column($findings, 'line'));
        self::assertSame(1, $findings[0]->value);
        self::assertSame(0, $findings[0]->threshold);
    }

    public function testExplicitTypesAreAccepted(): void
    {
        self::assertSame([], $this->findings('<?php function work(int|string $id, ?string $label, mixed ...$values) {}'));
    }

    public function testMixedPreservesUntypedInheritedAndTraitSignatures(): void
    {
        $findings = $this->findings('<?php
            class ParentModel { public function setTable($table) {} }
            trait TableSelection { public function setTable(mixed $table): static { return $this; } }
            class Model extends ParentModel { use TableSelection; }
        ');
        self::assertCount(1, $findings);
        self::assertSame(2, $findings[0]->line);
    }

    private function findings(string $source): array
    {
        $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse($source);

        return iterator_to_array((new NoUntypedParameters)->checkFile(new FileFacts('/project/input.php', $source, $nodes, [])));
    }
}
