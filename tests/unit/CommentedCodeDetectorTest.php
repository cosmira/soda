<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\CommentedCodeDetector;
use PHPUnit\Framework\TestCase;

final class CommentedCodeDetectorTest extends TestCase
{
    public function testInlineDocumentationIsNotDisabledCode(): void
    {
        self::assertFalse(CommentedCodeDetector::isCommentedCode('created from a method (e.g. `$obj->method(...)`), the method inherits attributes.'));
        self::assertFalse(CommentedCodeDetector::isCommentedCode('Use `new Worker()` to start processing.'));
        self::assertTrue(CommentedCodeDetector::isCommentedCode('$obj->method();'));
        self::assertTrue(CommentedCodeDetector::isCommentedCode('$result = run(); // see `run()`'));
    }
}
