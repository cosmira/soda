<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use function preg_match;
use function str_starts_with;
use function strlen;
use function trim;

final class CommentedCodeDetector
{
    /**
     * Defines explicit code patterns used by this policy.
     */
    private const array EXPLICIT_CODE_PATTERNS = [
        '/\b(?:final\s+|abstract\s+)?(?:class|interface|trait|enum)\s+[A-Za-z_\\\\]/',
        '/\bfunction\s+[A-Za-z_]/',
        '/\bfn\s*\(/',
        '/\bnew\s+[A-Za-z_\\\\][A-Za-z0-9_\\\\]*\s*\(/',
        '/\b(?:public|protected|private)\b(?:\s+(?:static|readonly|final))*\s+(?:function|\$|const)\b/',
    ];

    /**
     * Determine whether commented code applies to the supplied input.
     */
    public static function isCommentedCode(string $commentLine): bool
    {
        $line = trim(preg_replace('/`+[^`]*`+/', '', $commentLine) ?? $commentLine);

        if (self::isClearlyNotCode($line)) {
            return false;
        }

        if (self::isExplicitCodePatternMatch($line)) {
            return true;
        }

        return self::signalScore($line) >= 3;
    }

    /**
     * Determine whether clearly not code applies to the supplied input.
     */
    private static function isClearlyNotCode(string $line): bool
    {
        return $line === ''
            || strlen($line) < 3
            || str_starts_with($line, '@');
    }

    /**
     * Determine whether explicit code pattern match applies to the supplied input.
     */
    private static function isExplicitCodePatternMatch(string $line): bool
    {
        foreach (self::EXPLICIT_CODE_PATTERNS as $pattern) {
            if (self::isPatternMatch($pattern, $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Combine syntax-like signals to estimate whether a comment contains disabled code.
     */
    private static function signalScore(string $line): int
    {
        $score = 0;

        $score += self::isPatternMatch('/\$\w+/', $line) ? 2 : 0;
        $score += self::isPatternMatch('/(?:->|::)\s*[A-Za-z_]/', $line) ? 2 : 0;
        $score += self::isPatternMatch('/\b(?:if|elseif|foreach|for|while|switch|catch)\s*\(/', $line) ? 2 : 0;
        $score += self::isPatternMatch('/\b(?:return|throw|yield)\b/', $line) ? 1 : 0;
        $score += self::isPatternMatch('/=\s*[^=]/', $line) ? 1 : 0;
        $score += self::isPatternMatch('/[;{}]\s*$/', $line) ? 1 : 0;

        return $score + (self::isPatternMatch('/\b(?:null|true|false)\b/', $line) ? 1 : 0);
    }

    /**
     * Determine whether pattern match applies to the supplied input.
     */
    private static function isPatternMatch(string $pattern, string $line): bool
    {
        return preg_match($pattern, $line) === 1;
    }
}
