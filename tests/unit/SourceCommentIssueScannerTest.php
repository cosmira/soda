<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\SourceCommentIssueScanner;
use PHPUnit\Framework\TestCase;

final class SourceCommentIssueScannerTest extends TestCase
{
    public function testDetectsTodoAndFixmeComments(): void
    {
        $code = <<<'PHP'
<?php
// TODO remove fallback
/**
 * FIXME split this class
 */
function run(): void {}
PHP;

        $issues = SourceCommentIssueScanner::scan($code);

        $this->assertCount(2, $issues['todoFixme']);
        $this->assertSame('TODO', $issues['todoFixme'][0]['kind']);
        $this->assertSame(2, $issues['todoFixme'][0]['line']);
        $this->assertSame('FIXME', $issues['todoFixme'][1]['kind']);
        $this->assertSame(4, $issues['todoFixme'][1]['line']);
    }

    public function testDetectsCommentedOutCodeButIgnoresPlainLanguage(): void
    {
        $code = <<<'PHP'
<?php
// $user = $repo->find($id);
// return early if cache is warm
// if ($user->isActive()) {
PHP;

        $issues = SourceCommentIssueScanner::scan($code);

        $this->assertCount(2, $issues['commentedCode']);
        $this->assertSame('$user = $repo->find($id);', $issues['commentedCode'][0]['text']);
        $this->assertSame(2, $issues['commentedCode'][0]['line']);
        $this->assertSame('if ($user->isActive()) {', $issues['commentedCode'][1]['text']);
        $this->assertSame(4, $issues['commentedCode'][1]['line']);
    }

    public function testIgnoresDocblockNewExpressionButDetectsLineAndBlockComments(): void
    {
        $code = <<<'PHP'
<?php
/**
 * new ClassName()
 */
// new ClassName();
/*
new ClassName();
*/
PHP;

        $issues = SourceCommentIssueScanner::scan($code);

        $this->assertCount(2, $issues['commentedCode']);
        $this->assertSame('new ClassName();', $issues['commentedCode'][0]['text']);
        $this->assertSame(5, $issues['commentedCode'][0]['line']);
        $this->assertSame('new ClassName();', $issues['commentedCode'][1]['text']);
        $this->assertSame(7, $issues['commentedCode'][1]['line']);
    }
}
