<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Documentation;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpToken;

/**
 * Adjacent block comments should be one comment, with prose and tags together.
 */
#[Rule(
    label: 'Consecutive block comments:',
    advice: 'Merge adjacent block comments, keeping the description and PHPDoc tags together.',
)]
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
     * Standalone tool directives must remain separate tokens; PHPDoc tags still belong together.
     */
    private function isBlockComment(PhpToken $token): bool
    {
        return $token->id === T_DOC_COMMENT || ($token->id === T_COMMENT
            && str_starts_with($token->text, '/*')
            && preg_match('~^/\*\s*@[a-zA-Z][\w-]*\s*\*/$~D', $token->text) !== 1);
    }
}
