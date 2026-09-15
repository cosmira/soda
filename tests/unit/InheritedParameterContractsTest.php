<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Usage\NoUnusedParameters;
use PHPUnit\Framework\TestCase;

final class InheritedParameterContractsTest extends TestCase
{
    public function testContractAcrossFilesDoesNotDependOnFileOrder(): void
    {
        $sources = [
            '<?php namespace Vendor; interface Abilities { public function can(string $ability): bool; }',
            '<?php namespace App; use Vendor\Abilities; class Token implements Abilities { public function can(string $ability): bool { return true; } }',
        ];
        self::assertSame([], $this->findings($sources));
        self::assertSame([], $this->findings(array_reverse($sources)));
    }

    public function testAbstractEnginePreservesInputsButNotExtraPositions(): void
    {
        $sources = [
            '<?php namespace App; abstract class Engine { abstract public function update($models); }',
            '<?php namespace App; abstract class Intermediate extends Engine {}',
            '<?php namespace App; class NullEngine extends Intermediate { public function update($models, $extra = null) {} }',
        ];
        $findings = $this->findings($sources);
        self::assertCount(1, $findings);
        self::assertSame('App\NullEngine', $findings[0]->class);
        self::assertStringContainsString('$extra', $findings[0]->message);
    }

    public function testUnknownOrPrivateParentDoesNotInventContract(): void
    {
        self::assertCount(1, $this->findings(['<?php class Child extends Unknown { function run($unused) {} }']));
        $findings = $this->findings([
            '<?php class ParentType { private function run($input) { return $input; } }',
            '<?php class Child extends ParentType { public function run($unused) {} }',
        ]);
        self::assertCount(1, $findings);
    }

    public function testConcreteConstructorCanChangeItsSignature(): void
    {
        self::assertCount(1, $this->findings([
            '<?php class ParentType { function __construct($input) { consume($input); } }',
            '<?php class Child extends ParentType { function __construct($unused) {} }',
        ]));
        self::assertSame([], $this->findings([
            '<?php abstract class ParentType { abstract public function __construct($input); }',
            '<?php class Child extends ParentType { public function __construct($unused) {} }',
        ]));
    }

    public function testBaseInputUsedByPolymorphicOverrideIsPreserved(): void
    {
        $sources = [
            '<?php class Engine { public function mapIdsFrom($results, $key) { return $results; } }',
            '<?php class Concrete extends Engine { public function mapIdsFrom($results, $key) { return extractIds($results, $key); } }',
        ];
        self::assertSame([], $this->findings($sources));
        self::assertSame([], $this->findings(array_reverse($sources)));
        $sources[1] = '<?php class Unrelated { public function mapIdsFrom($results, $key) { return extractIds($results, $key); } }';
        self::assertCount(1, $this->findings($sources));
    }

    private function findings(array $sources): array
    {
        $paths = [];

        try {
            foreach ($sources as $source) {
                $path = tempnam(sys_get_temp_dir(), 'soda-inherited-input-');
                $paths[] = $path;
                file_put_contents($path, $source);
            }

            $result = (new Runner)->check($paths, Soda::configure()->with([new NoUnusedParameters]));

            return $result->violations->all();
        } finally {
            foreach ($paths as $path) {
                unlink($path);
            }
        }
    }
}
