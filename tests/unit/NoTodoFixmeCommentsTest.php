<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class NoTodoFixmeCommentsTest extends TestCase
{
    public function testReportsEveryTodoCommentWithoutNumericAllowance(): void
    {
        $violations = CheckFixture::forRule(null, $this->context(['max_todo_fixme_comments' => 0]));

        $this->assertCount(2, $violations);
        $this->assertSame('max_todo_fixme_comments', $violations[1]->rule);
        $this->assertSame('/project/src/File.php', $violations[1]->file);
        $this->assertSame(8, $violations[1]->line);
        $this->assertSame(['value' => 2, 'threshold' => 0], ['value' => $violations[1]->value, 'threshold' => $violations[1]->threshold]);
        $this->assertSame('FIXME comment: FIXME handle retry errors', $violations[1]->message);
    }

    public function testReturnsNoViolationsWhenRuleIsDisabled(): void
    {
        $violations = CheckFixture::forRule(null, $this->context([]));

        $this->assertTrue($violations->isEmpty());
    }

    /**
     * @param array<string, int> $rules
     */
    private function context(array $rules): array
    {
        $core = [
            '/project/src/File.php' => [
                'file_loc'      => 10,
                'classes_count' => 0,
                'classes'       => [],
                'methods'       => [],
                'namespaces'    => [],
                'todoFixme'     => [
                    ['line' => 3, 'kind' => 'TODO', 'text' => 'TODO remove fallback'],
                    ['line' => 8, 'kind' => 'FIXME', 'text' => 'FIXME handle retry errors'],
                ],
            ],
        ];

        $fileMetrics = $core;

        return [CheckFixture::checks($rules, []), $fileMetrics];
    }
}
