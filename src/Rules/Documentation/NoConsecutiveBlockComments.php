<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Documentation;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use PhpToken;

/**
 * Adjacent block comments should be one comment, with prose and tags together.
 */
final class NoConsecutiveBlockComments extends Check
{
    /**
     * Return the stable identifier used in diagnostics and configuration.
     */
    #[\Override]
    public function id(): string
    {
        return 'no_consecutive_block_comments';
    }

    /**
     * Inspect comment tokens in the source without requesting metric collectors.
     *
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Report every additional block in a sequence separated only by whitespace.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        $previousBlock = false;
        foreach (PhpToken::tokenize($file->source) as $token) {
            if ($token->id === T_WHITESPACE) {
                continue;
            }

            $block = $this->isBlockComment($token);
            if ($previousBlock && $block) {
                yield new Violation(
                    rule: $this->id(), file: $file->path, value: 1, threshold: 0, line: $token->line,
                    message: 'Merge consecutive block comments into one comment; keep the description and PHPDoc tags together.',
                );
            }
            $previousBlock = $block;
        }
    }

    /**
     * Line comments are outside this policy, even when adjacent to a PHPDoc.
     */
    private function isBlockComment(PhpToken $token): bool
    {
        return $token->id === T_DOC_COMMENT || ($token->id === T_COMMENT && str_starts_with($token->text, '/*'));
    }
}
