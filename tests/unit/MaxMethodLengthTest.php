<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class MaxMethodLengthTest extends TestCase
{
    public function testReturnsViolationWhenMethodLengthExceedsLimit(): void
    {
        $violations = CheckFixture::run(CheckFixture::checks(['max_method_length' => 10], []), ['/project/src/File.php' => ['methods' => [
            'App\Service::handle' => ['loc' => 25, 'args' => 1],
        ]]]);

        $this->assertCount(1, $violations);
        $this->assertSame('max_method_length', $violations[0]->rule);
        $this->assertSame('/project/src/File.php', $violations[0]->file);
        $this->assertSame('App\Service::handle', $violations[0]->method);
        $this->assertSame('App\Service', $violations[0]->class);
        $this->assertSame(['value' => 25, 'threshold' => 10], ['value' => $violations[0]->value, 'threshold' => $violations[0]->threshold]);
    }

    public function testReturnsNoViolationsWhenRuleIsDisabled(): void
    {
        $violations = CheckFixture::run(CheckFixture::checks([], []), ['/project/src/File.php' => ['methods' => [
            'App\Service::handle' => ['loc' => 25, 'args' => 1],
        ]]]);

        $this->assertTrue($violations->isEmpty());
    }
}
