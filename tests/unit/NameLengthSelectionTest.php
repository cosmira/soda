<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Rules\Naming\ClassNameLength;
use Cosmira\Soda\Rules\Naming\MethodNameLength;
use Cosmira\Soda\Rules\Naming\NamespaceNameLength;
use Cosmira\Soda\Rules\Naming\VariableNameLength;
use PHPUnit\Framework\TestCase;

final class NameLengthSelectionTest extends TestCase
{
    public function testCollectsOnlyEnabledNameKinds(): void
    {
        $visitor = $this->collect(
            <<<'PHP'
            class User
            {
                public function run(): void
                {
                    $value = 1;
                }
            }
            PHP,
            ['method_name_length', 'class_name_length'],
        );

        $this->assertSame([
            ['class_name_length', 'Name "User" has length 4.', 1],
            ['method_name_length', 'Name "run" has length 3.', 3],
        ], $visitor);
    }

    public function testSkipsDynamicVariablesAndAnonymousClasses(): void
    {
        $visitor = $this->collect(
            <<<'PHP'
            $name = 'value';
            $$name = 1;
            $object = new class {
                public function run(): void {}
            };
            PHP,
            ['variable_name_length', 'class_name_length'],
        );

        $this->assertSame([
            ['variable_name_length', 'Name "name" has length 4.', 1],
            ['variable_name_length', 'Name "object" has length 6.', 3],
        ], $visitor);
    }

    public function testCollectsNamespaceSegments(): void
    {
        $visitor = $this->collect(
            <<<'PHP'
            namespace App\X\Domain;

            final class User {}
            PHP,
            ['namespace_name_length'],
        );

        $this->assertSame([
            ['namespace_name_length', 'Name "App" has length 3.', 1],
            ['namespace_name_length', 'Name "X" has length 1.', 1],
            ['namespace_name_length', 'Name "Domain" has length 6.', 1],
        ], $visitor);
    }

    private function collect(string $code, array $rules): array
    {
        $available = [
            'class_name_length'     => new ClassNameLength(1000, 1001),
            'method_name_length'    => new MethodNameLength(1000, 1001),
            'namespace_name_length' => new NamespaceNameLength(1000, 1001),
            'variable_name_length'  => new VariableNameLength(1000, 1001),
        ];
        $path = tempnam(sys_get_temp_dir(), 'soda-name-selection-');
        file_put_contents($path, '<?php '.$code);

        try {
            $violations = CheckFixture::collect($path, array_map(fn (string $id) => $available[$id], $rules))['violations'];
        } finally {
            unlink($path);
        }

        usort($violations, static fn ($left, $right): int => $left->line <=> $right->line);

        return array_map(static fn ($violation): array => [$violation->rule, $violation->message, $violation->line], $violations);
    }
}
