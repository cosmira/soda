<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Rules\Architecture\NoObjectCloning;
use Cosmira\Soda\Rules\Complexity\MaxReturnStatements;
use Cosmira\Soda\Rules\Complexity\NoElseBranches;
use Cosmira\Soda\Rules\Usage\OnlyListArraysAllowed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PracticalPolicyCompatibilityTest extends TestCase
{
    #[DataProvider('policyCases')]
    public function testDocumentsPolicyLimitsWithoutSilentlyChangingTheirMeaning(string $code, array $rules, array $expected): void
    {
        $path = tempnam(sys_get_temp_dir(), 'soda-policy-');
        file_put_contents($path, '<?php '.$code);

        try {
            $result = CheckFixture::collect($path, $rules);
            $actual = array_map(fn ($violation): array => [$violation->rule, $violation->value, $violation->threshold], $result['violations']);
            self::assertSame($expected, $actual);
        } finally {
            unlink($path);
        }
    }

    public static function policyCases(): iterable
    {
        yield 'early exits remain counted' => [
            'function label(int $value): string { if ($value === 1) return "one"; if ($value === 2) return "two"; if ($value === 3) return "three"; if ($value === 4) return "four"; if ($value === 5) return "five"; return "other"; }',
            [new NoElseBranches, new MaxReturnStatements(4)],
            [['max_return_statements', 6, 4]],
        ];
        yield 'explicit fluent copy is still a clone under the strict rule' => [
            'final class Options { private int $limit = 10; public function limitedTo(int $limit): self { $copy = clone $this; $copy->limit = $limit; return $copy; } }',
            [new NoObjectCloning],
            [['no_object_cloning', 1, 0]],
        ];
        yield 'fresh value construction satisfies the clone policy' => [
            'final readonly class Options { public function __construct(public int $limit = 10) {} public function limitedTo(int $limit): self { return new self($limit); } }',
            [new NoObjectCloning],
            [],
        ];
        yield 'direct nested access is syntax evidence' => [
            'function userId(array $row): int { return $row["user"]["id"]; }',
            [new OnlyListArraysAllowed],
            [['only_list_arrays', 1, 0]],
        ];
        yield 'a temporary does not create a better domain model' => [
            'function userId(array $row): int { $user = $row["user"]; return $user["id"]; }',
            [new OnlyListArraysAllowed],
            [],
        ];
    }
}
