<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use function array_fill_keys;
use function array_keys;
use function count;
use function explode;
use function is_array;
use function substr_count;
use function token_get_all;
use function trim;

/** Maps source lines that contain executable or declarative PHP syntax. */
final readonly class LogicalLineMap
{
    /**
     * @param array<int, true> $codeLines
     */
    private function __construct(private array $codeLines) {}

    /**
     * Build a map from PHP source while ignoring whitespace and comments.
     */
    public static function fromSource(string $source): self
    {
        $lines = [];
        $currentLine = 1;

        foreach (token_get_all($source) as $token) {
            $text = $token;
            $startLine = $currentLine;

            if (is_array($token)) {
                [, $text, $startLine] = $token;
            }

            self::recordCodeToken($lines, $token, $text, $startLine);

            $currentLine = $startLine + substr_count($text, "\n");
        }

        return new self(array_fill_keys(array_keys($lines), true));
    }

    /**
     * Return the total number of logical code lines.
     */
    public function count(): int
    {
        return count($this->codeLines);
    }

    /**
     * Count logical code lines inside an inclusive source range.
     */
    public function countBetween(int $startLine, int $endLine): int
    {
        $count = 0;

        foreach (array_keys($this->codeLines) as $line) {
            $isWithinRange = $line >= $startLine && $line <= $endLine;
            if ($isWithinRange) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<int, true>                        $lines
     * @param string|array{0: int, 1: string, 2: int} $token
     */
    private static function recordCodeToken(array &$lines, string|array $token, string $text, int $startLine): void
    {
        $isCodeToken = self::isCodeToken($token);
        if (! $isCodeToken) {
            return;
        }

        foreach (explode("\n", $text) as $offset => $part) {
            $hasCode = trim($part) !== '';
            if ($hasCode) {
                $lines[$startLine + $offset] = true;
            }
        }
    }

    /**
     * @param string|array{0: int, 1: string, 2: int} $token
     */
    private static function isCodeToken(string|array $token): bool
    {
        $isArray = is_array($token);
        if (! $isArray) {
            return trim($token) !== '';
        }

        [$tokenId] = $token;

        return ! in_array($tokenId, [
            T_WHITESPACE,
            T_COMMENT,
            T_DOC_COMMENT,
            T_OPEN_TAG,
            T_CLOSE_TAG,
        ], true);
    }
}
