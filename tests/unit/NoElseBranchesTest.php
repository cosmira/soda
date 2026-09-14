<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Rules\Complexity\NoElseBranches;
use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class NoElseBranchesTest extends TestCase
{
    public function testReportsElseifAndElseAtTheirExactLines(): void
    {
        $result = $this->analyse(<<<'PHP'
<?php
if ($first) {
    return 1;
} elseif ($second) {
    return 2;
} else {
    return 3;
}
PHP);

        $this->assertSame(
            [['line' => 4, 'kind' => 'elseif'], ['line' => 6, 'kind' => 'else']],
            $result['metrics']['elseBranches'],
        );
        $this->assertSame([4, 6], array_map(static fn ($violation): ?int => $violation->line, $result['violations']));
        $this->assertStringContainsString('Avoid elseif', (string) $result['violations'][0]->message);
        $this->assertStringContainsString('Avoid else', (string) $result['violations'][1]->message);
    }

    public function testReportsEveryNestedElseFromAccessCheckExample(): void
    {
        $result = $this->analyse(<<<'PHP'
<?php
function hasAccess(User $user): bool {
    if (!$user->isBanned()) {
        if ($user->isAdmin()) {
            return true;
        } else {
            if ($user->isGranted(GRANT::EDIT)) {
                return true;
            } else {
                return false;
            }
        }
    } else {
        return false;
    }
}
PHP);

        $this->assertSame([6, 9, 13], array_column($result['metrics']['elseBranches'], 'line'));
        $this->assertCount(3, $result['violations']);
    }

    public function testAllowsGuardClausesAndDoesNotConfuseOtherAlternativesWithElse(): void
    {
        $result = $this->analyse(<<<'PHP'
<?php
function hasAccess(User $user): bool {
    if ($user->isBanned()) {
        return false;
    }

    if ($user->isAdmin()) {
        return true;
    }

    try {
        $role = match ($user->role()) {
            'editor' => true,
            default => false,
        };
    } catch (RuntimeException) {
        return false;
    }

    return $role ? $user->isGranted(GRANT::EDIT) : false;
}
PHP);

        $this->assertSame([], $result['metrics']['elseBranches']);
        $this->assertSame([], $result['violations']);
    }

    /** @return array{metrics: array, violations: array} */
    private function analyse(string $source): array
    {
        $path = tempnam(sys_get_temp_dir(), 'soda_no_else_');
        $this->assertNotFalse($path);
        file_put_contents($path, $source);

        try {
            $result = CheckFixture::collect($path, [new NoElseBranches]);
        } finally {
            unlink($path);
        }

        return ['metrics' => $result['metrics'], 'violations' => $result['violations']];
    }
}
